<?php

declare(strict_types=1);

namespace App\Scraper;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Scrapes metadata from Archive of Our Own (AO3) work pages.
 *
 * CRITICAL GUARDRAIL — METADATA ONLY:
 * This scraper operates solely on the work's landing page metadata block.
 * It must NEVER navigate to chapter pages or extract any story/prose content.
 * Only the following metadata is extracted: title, authors, summary, tags,
 * word count, chapter count, dates, series info, language, and source URL.
 */
class Ao3Scraper implements ScraperInterface
{
    private const AO3_HOST = 'archiveofourown.org';

    /** Retry-After values above this cap are treated as absent (null returned). */
    private const RETRY_AFTER_CAP_SECONDS = 120;

    /**
     * Timestamp (microtime float) of the last outbound HTTP request made by this instance.
     * Null until the first request. Persists across calls within the same process lifetime,
     * so a Messenger worker correctly throttles across consecutive messages.
     */
    private ?float $lastRequestAt = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'SCRAPER_USER_AGENT')]
        private readonly string $userAgent,
        #[Autowire(env: 'int:SCRAPER_REQUEST_DELAY_MS')]
        private readonly int $requestDelayMs,
    ) {
    }

    public function supports(string $url): bool
    {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        if ($host !== self::AO3_HOST && $host !== 'www.' . self::AO3_HOST) {
            return false;
        }

        $path = $parsed['path'] ?? '';

        return (bool) preg_match('#^/works/\d+#', $path);
    }

    /**
     * Fetches and parses the work page only, without fetching series data.
     *
     * The returned DTO will have seriesUrl populated if the work belongs to a series,
     * but seriesNumberOfParts, seriesTotalWords, and seriesIsComplete will be null.
     *
     * This method is intentionally not on ScraperInterface — it is an AO3-specific
     * implementation detail that may not generalise to other scrapers. The future batch
     * orchestrator will depend on the concrete Ao3Scraper directly.
     *
     * @throws \InvalidArgumentException if the URL is not a supported AO3 work URL
     * @throws RateLimitException if AO3 returns 429, 503, 502, or 504
     * @throws ScrapingException if AO3 returns any other non-200 status
     * @throws TransportExceptionInterface if the HTTP request cannot be completed
     */
    public function scrapeWorkPage(string $url): ScrapedWorkDto
    {
        // Explicitly guard against non-AO3 URLs. This makes the security boundary
        // intentional and visible — do not rely on normalizeUrl() to enforce it,
        // as that would be accidental protection that could silently disappear on refactor.
        if (!$this->supports($url)) {
            throw new \InvalidArgumentException(
                sprintf('Ao3Scraper does not support URL: %s', $url),
            );
        }

        $normalizedUrl = $this->normalizeUrl($url);

        $this->logger->debug('AO3 scraper: fetching work page', ['url' => $normalizedUrl]);

        $html = $this->fetchUrl($normalizedUrl);

        $this->logger->debug('AO3 scraper: fetched work page HTML', [
            'url' => $normalizedUrl,
            'bytes' => strlen($html),
        ]);

        return $this->parse($html, $normalizedUrl);
    }

    /**
     * Fetches and parses a work page, then fetches series data if the work belongs to a series.
     *
     * This is the interface-contract method. Its sync behavior is identical to before:
     * one call, one DTO returned, series fields populated when available.
     *
     * Known limitation: if the work page fetch succeeds but the series fetch throws
     * RateLimitException, the already-scraped work data is discarded and the caller must
     * retry the entire operation. This is acceptable for the sync path — rate limits
     * mid-scrape are rare, the doubled request is a single extra HTTP call, and preserving
     * partial state would add unjustified complexity for a one-at-a-time user-facing flow.
     * The async path avoids this entirely by calling scrapeWorkPage() directly.
     *
     * @throws \InvalidArgumentException if the URL is not a supported AO3 work URL
     * @throws RateLimitException if AO3 returns 429, 503, 502, or 504
     * @throws ScrapingException if AO3 returns any other non-200 status
     * @throws TransportExceptionInterface if an HTTP request cannot be completed
     */
    public function scrape(string $url): ScrapedWorkDto
    {
        $dto = $this->scrapeWorkPage($url);

        if ($dto->seriesUrl !== null) {
            $seriesData = $this->fetchSeriesData($dto->seriesUrl);
            $dto->seriesNumberOfParts = $seriesData['numberOfParts'];
            $dto->seriesTotalWords    = $seriesData['totalWords'];
            $dto->seriesIsComplete    = $seriesData['isComplete'];
        }

        return $dto;
    }

    /**
     * Performs a single outbound HTTP GET with proactive rate-limit throttling.
     *
     * Throttle: sleeps only the remaining portion of $requestDelayMs that hasn't elapsed
     * since the last request. If more time has already passed (or this is the first call),
     * sleeps zero. Updates $lastRequestAt after every successful response.
     *
     * Response handling:
     * - 429 / 503: throws RateLimitException with Retry-After seconds (capped at 120 s; null if absent or over cap)
     * - 502 / 504: throws RateLimitException with null (transient infra errors, same retry strategy)
     * - any other non-200: throws ScrapingException
     * - TransportExceptionInterface: propagates uncaught (no response received — caller decides)
     *
     * @throws RateLimitException
     * @throws ScrapingException
     * @throws TransportExceptionInterface
     */
    private function fetchUrl(string $url): string
    {
        $this->throttle();

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'User-Agent' => $this->userAgent,
                // AO3 redirects canonical work URLs to /chapters/{id} and drops query
                // params. Sending view_adult as a cookie ensures the adult-content
                // bypass persists through the entire redirect chain.
                'Cookie' => 'view_adult=true',
            ],
        ]);

        // getStatusCode() may throw TransportExceptionInterface for async clients — let it propagate.
        $statusCode = $response->getStatusCode();
        $this->lastRequestAt = microtime(true);

        $finalUrl = $response->getInfo('url') ?? $url;
        $this->logger->debug('AO3 scraper: received response', [
            'requested_url' => $url,
            'final_url' => $finalUrl,
            'http_status' => $statusCode,
            'redirected' => $finalUrl !== $url,
        ]);

        if ($statusCode === 200) {
            return $response->getContent();
        }

        if ($statusCode === 429 || $statusCode === 503) {
            $retryAfter = $this->parseRetryAfter($response->getHeaders(false));
            throw new RateLimitException($url, $retryAfter);
        }

        if ($statusCode === 502 || $statusCode === 504) {
            // Transient Cloudflare/infrastructure errors — indistinguishable from a rate limit
            // in practice; apply the same backoff-with-jitter strategy.
            throw new RateLimitException($url, null);
        }

        throw new ScrapingException(
            $url,
            sprintf('AO3 returned HTTP %d for URL: %s', $statusCode, $url),
            $statusCode,
        );
    }

    /**
     * Sleeps for the remaining portion of $requestDelayMs that hasn't elapsed
     * since the last outbound request. No-op on the first call or if enough time
     * has already passed.
     */
    private function throttle(): void
    {
        if ($this->requestDelayMs <= 0 || $this->lastRequestAt === null) {
            return;
        }

        $elapsedMs = (int) ((microtime(true) - $this->lastRequestAt) * 1000);
        $remainingMs = $this->requestDelayMs - $elapsedMs;

        if ($remainingMs > 0) {
            usleep($remainingMs * 1000);
        }
    }

    /**
     * Parses the Retry-After response header.
     * Returns null if absent or if the value exceeds RETRY_AFTER_CAP_SECONDS.
     * Values over the cap are logged so the operator knows AO3's instruction was received.
     *
     * @param array<string, list<string>> $headers
     */
    private function parseRetryAfter(array $headers): ?int
    {
        // Header names are lowercased by Symfony's HTTP client.
        $values = $headers['retry-after'] ?? [];
        if ($values === []) {
            return null;
        }

        $seconds = (int) $values[0];
        if ($seconds > self::RETRY_AFTER_CAP_SECONDS) {
            $this->logger->warning('AO3 scraper: Retry-After exceeds cap; treating as null', [
                'retry_after' => $seconds,
                'cap' => self::RETRY_AFTER_CAP_SECONDS,
            ]);

            return null;
        }

        return $seconds > 0 ? $seconds : null;
    }

    private function normalizeUrl(string $url): string
    {
        // Ensure https scheme
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        // Extract work ID from path and build canonical URL
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Strip anything after /works/{id} (e.g. /chapters/...) to stay on the works page
        if (preg_match('#(/works/\d+)#', $path, $matches)) {
            $path = $matches[1];
        }

        return 'https://' . self::AO3_HOST . $path;
    }

    private function parse(string $html, string $sourceUrl): ScrapedWorkDto
    {
        $crawler = new Crawler($html);
        $dto = new ScrapedWorkDto();
        $dto->sourceUrl = $sourceUrl;
        $dto->sourceType = 'AO3';
        $dto->workType = 'Fanfiction';

        $dto->title = $this->parseTitle($crawler);
        $dto->authors = $this->parseAuthors($crawler);
        $dto->summary = $this->parseSummary($crawler);
        $dto->language = $this->parseLanguage($crawler);
        $dto->words = $this->parseWords($crawler);

        [$chapters, $totalChapters] = $this->parseChapters($crawler);
        $dto->chapters = $chapters;
        $dto->totalChapters = $totalChapters;
        $dto->isComplete = $this->parseIsComplete($crawler, $totalChapters);

        $dto->publishedDate = $this->parseDate($crawler, 'dd.published');
        $dto->lastUpdatedDate = $this->parseUpdatedDate($crawler);

        [$seriesName, $seriesUrl, $placeInSeries] = $this->parseSeries($crawler);
        $dto->seriesName = $seriesName;
        $dto->seriesUrl = $seriesUrl;
        $dto->placeInSeries = $placeInSeries;

        $dto->metadata = $this->parseMetadata($crawler);

        return $dto;
    }

    private function parseTitle(Crawler $crawler): ?string
    {
        try {
            $titleNode = $crawler->filter('h2.title.heading');
            if ($titleNode->count() === 0) {
                return null;
            }

            return trim($titleNode->text());
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse title', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return list<array{name: string, link: string|null}> */
    private function parseAuthors(Crawler $crawler): array
    {
        try {
            $authors = [];
            $crawler->filter('a[rel="author"]')->each(function (Crawler $node) use (&$authors): void {
                $name = trim($node->text());
                if ($name !== '') {
                    $href = $node->attr('href');
                    $authors[] = [
                        'name' => $name,
                        'link' => $href !== null ? 'https://' . self::AO3_HOST . $href : null,
                    ];
                }
            });

            return $authors;
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse authors', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function parseSummary(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('.summary .userstuff');
            if ($node->count() === 0) {
                return null;
            }

            return trim($node->text());
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse summary', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function parseLanguage(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('dd.language');
            if ($node->count() === 0) {
                return null;
            }

            return trim($node->text());
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse language', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function parseWords(Crawler $crawler): ?int
    {
        try {
            $node = $crawler->filter('dd.words');
            if ($node->count() === 0) {
                return null;
            }

            // AO3 formats word counts with commas, e.g. "123,456"
            $text = str_replace(',', '', trim($node->text()));

            return (int) $text ?: null;
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse words', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Returns [chapters, totalChapters] where totalChapters is null for ongoing works ("X/?").
     *
     * @return array{int|null, int|null}
     */
    private function parseChapters(Crawler $crawler): array
    {
        try {
            $node = $crawler->filter('dd.chapters');
            if ($node->count() === 0) {
                return [null, null];
            }

            // Format is "X/Y" for complete or "X/?" for ongoing
            $text = trim($node->text());
            if (!str_contains($text, '/')) {
                $val = (int) $text;

                return [$val ?: null, $val ?: null];
            }

            [$published, $total] = explode('/', $text, 2);
            $publishedInt = (int) trim($published) ?: null;
            $totalInt = trim($total) === '?' ? null : ((int) trim($total) ?: null);

            return [$publishedInt, $totalInt];
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse chapters', ['error' => $e->getMessage()]);

            return [null, null];
        }
    }

    private function parseIsComplete(Crawler $crawler, ?int $totalChapters): bool
    {
        try {
            // If totalChapters is known (not "?"), check dd.status for explicit "Completed"
            $node = $crawler->filter('dd.status');
            if ($node->count() > 0) {
                return strtolower(trim($node->text())) === 'completed';
            }

            // Single-chapter works (no status dd) are complete when chapters == 1/1
            return $totalChapters !== null && $totalChapters === 1;
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse completion status', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function parseDate(Crawler $crawler, string $selector): ?string
    {
        try {
            $node = $crawler->filter($selector);
            if ($node->count() === 0) {
                return null;
            }

            return trim($node->text());
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf('AO3 scraper: failed to parse date from selector "%s"', $selector),
                ['error' => $e->getMessage()],
            );

            return null;
        }
    }

    private function parseUpdatedDate(Crawler $crawler): ?string
    {
        // dd.status contains the last-updated date for multi-chapter works.
        // For single-chapter or complete works it may say "Completed" instead.
        // Fall back to published date if not a date string.
        try {
            $node = $crawler->filter('dd.status');
            if ($node->count() === 0) {
                return null;
            }

            $text = trim($node->text());
            // If the text looks like a date (YYYY-MM-DD), use it; otherwise return null
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                return $text;
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse updated date', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Returns [seriesName, seriesUrl, placeInSeries].
     *
     * @return array{string|null, string|null, int|null}
     */
    private function parseSeries(Crawler $crawler): array
    {
        try {
            $node = $crawler->filter('dd.series');
            if ($node->count() === 0) {
                return [null, null, null];
            }

            $seriesName = null;
            $seriesUrl = null;
            $placeInSeries = null;

            $linkNode = $node->filter('a');
            if ($linkNode->count() > 0) {
                $seriesName = trim($linkNode->first()->text());
                $href = $linkNode->first()->attr('href');
                if ($href !== null) {
                    $seriesUrl = str_starts_with($href, 'http')
                        ? $href
                        : 'https://' . self::AO3_HOST . $href;
                }
            }

            // The position text typically appears as "Part X of <series>"
            $fullText = trim($node->text());
            if (preg_match('/Part\s+(\d+)/i', $fullText, $matches)) {
                $placeInSeries = (int) $matches[1];
            }

            return [$seriesName, $seriesUrl, $placeInSeries];
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse series', ['error' => $e->getMessage()]);

            return [null, null, null];
        }
    }

    /**
     * Fetches the AO3 series page and extracts series-level metadata.
     * Returns null for any field that cannot be determined.
     *
     * Unlike the work page fetch, transport failures and rate limits are allowed to propagate —
     * the caller (scrape()) handles them uniformly.
     *
     * @return array{numberOfParts: int|null, totalWords: int|null, isComplete: bool|null}
     *
     * @throws RateLimitException
     * @throws ScrapingException
     * @throws TransportExceptionInterface
     */
    private function fetchSeriesData(string $seriesUrl): array
    {
        $this->logger->debug('AO3 scraper: fetching series page', ['url' => $seriesUrl]);

        $html = $this->fetchUrl($seriesUrl);

        return $this->parseSeriesPage($html);
    }

    /**
     * Parses series metadata from the AO3 series page HTML.
     *
     * All three fields are scoped to dl.series.meta.group dl.stats to avoid collisions
     * with the per-work dl.stats blocks that appear in the series work listing below.
     * The "Complete:" dt has no class, so it is located via XPath.
     *
     * @return array{numberOfParts: int|null, totalWords: int|null, isComplete: bool|null}
     */
    private function parseSeriesPage(string $html): array
    {
        $result = ['numberOfParts' => null, 'totalWords' => null, 'isComplete' => null];

        try {
            $crawler = new Crawler($html);

            // Scope to the series metadata stats block. The series work listing below
            // also contains dl.stats elements (one per work), so without this scoping
            // the first filter match would be an individual work's stats, not the series total.
            $statsBlock = $crawler->filter('dl.series.meta.group dl.stats');
            if ($statsBlock->count() === 0) {
                $this->logger->warning('AO3 scraper: series metadata stats block not found');

                return $result;
            }

            $worksNode = $statsBlock->filter('dd.works');
            if ($worksNode->count() > 0) {
                $text = str_replace(',', '', trim($worksNode->text()));
                $result['numberOfParts'] = (int) $text ?: null;
            }

            $wordsNode = $statsBlock->filter('dd.words');
            if ($wordsNode->count() > 0) {
                $text = str_replace(',', '', trim($wordsNode->text()));
                $result['totalWords'] = (int) $text ?: null;
            }

            // "Complete:" dt has no class; XPath targets it by text content.
            $completeNode = $crawler->filterXPath(
                '//dl[contains(@class,"series") and contains(@class,"meta") and contains(@class,"group")]'
                . '//dl[contains(@class,"stats")]'
                . '//dt[normalize-space(text())="Complete:"]/following-sibling::dd[1]',
            );
            if ($completeNode->count() > 0) {
                $result['isComplete'] = strtolower(trim($completeNode->text())) === 'yes';
            }
        } catch (\Throwable $e) {
            $this->logger->warning('AO3 scraper: failed to parse series page', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Returns metadata grouped by AO3 category name.
     * Each entry is {name: string, link: string|null}.
     *
     * @return array<string, list<array{name: string, link: string|null}>>
     */
    private function parseMetadata(Crawler $crawler): array
    {
        $metadata = [];

        $categorySelectors = [
            'Rating' => 'dd.rating.tags a.tag',
            'Warning' => 'dd.warning.tags a.tag',
            'Category' => 'dd.category.tags a.tag',
            'Fandom' => 'dd.fandom.tags a.tag',
            'Relationship' => 'dd.relationship.tags a.tag',
            'Character' => 'dd.character.tags a.tag',
            'Tag' => 'dd.freeform.tags a.tag',
        ];

        foreach ($categorySelectors as $category => $selector) {
            try {
                $tags = [];
                $crawler->filter($selector)->each(function (Crawler $node) use (&$tags): void {
                    $name = trim($node->text());
                    if ($name !== '') {
                        $href = $node->attr('href');
                        $tags[] = [
                            'name' => $name,
                            'link' => $href !== null ? 'https://' . self::AO3_HOST . $href : null,
                        ];
                    }
                });

                if ($tags !== []) {
                    $metadata[$category] = $tags;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(
                    sprintf('AO3 scraper: failed to parse metadata category "%s"', $category),
                    ['error' => $e->getMessage()],
                );
            }
        }

        return $metadata;
    }
}
