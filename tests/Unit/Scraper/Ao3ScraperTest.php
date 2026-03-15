<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scraper;

use App\Scraper\Ao3Scraper;
use App\Scraper\ScrapedWorkDto;
use App\Scraper\ScrapingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class Ao3ScraperTest extends TestCase
{
    private function makeScraper(string $html, int $status = 200): Ao3Scraper
    {
        $response = new MockResponse($html, ['http_code' => $status]);
        $client = new MockHttpClient($response);

        return new Ao3Scraper($client, new NullLogger(), 'ReadingStats/test');
    }

    private function fixture(string $name): string
    {
        $path = __DIR__ . '/../../Fixtures/ao3/' . $name . '.html';

        return (string) file_get_contents($path);
    }

    // --- supports() ---

    /** @return array<string, array{string, bool}> */
    public static function supportsProvider(): array
    {
        return [
            'ao3 work' => ['https://archiveofourown.org/works/12345', true],
            'ao3 work with chapter' => ['https://archiveofourown.org/works/12345/chapters/67890', true],
            'ao3 www' => ['https://www.archiveofourown.org/works/12345', true],
            'ao3 series (not a work)' => ['https://archiveofourown.org/series/12345', false],
            'ao3 user' => ['https://archiveofourown.org/users/foo', false],
            'unrelated url' => ['https://example.com/works/12345', false],
            'ffn url' => ['https://www.fanfiction.net/s/12345', false],
            'no host' => ['notaurl', false],
        ];
    }

    #[DataProvider('supportsProvider')]
    public function test_supports(string $url, bool $expected): void
    {
        $scraper = $this->makeScraper('');
        $this->assertSame($expected, $scraper->supports($url));
    }

    // --- scrape(): complete work ---

    public function test_scrape_complete_work_title(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame('Test Complete Work Title', $dto->title);
    }

    public function test_scrape_complete_work_author(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame(['TestAuthor'], $dto->authors);
    }

    public function test_scrape_complete_work_summary(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertStringContainsString('summary of the complete test work', $dto->summary ?? '');
    }

    public function test_scrape_complete_work_words(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        // "12,345" → 12345
        $this->assertSame(12345, $dto->words);
    }

    public function test_scrape_complete_work_chapters(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame(5, $dto->chapters);
        $this->assertSame(5, $dto->totalChapters);
        $this->assertTrue($dto->isComplete);
    }

    public function test_scrape_complete_work_dates(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame('2023-01-15', $dto->publishedDate);
    }

    public function test_scrape_complete_work_language(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame('English', $dto->language);
    }

    public function test_scrape_complete_work_source_type(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertSame('AO3', $dto->sourceType);
        $this->assertSame('Fanfiction', $dto->workType);
    }

    public function test_scrape_complete_work_metadata(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertArrayHasKey('Rating', $dto->metadata);
        $this->assertContains('General Audiences', $dto->metadata['Rating']);

        $this->assertArrayHasKey('Fandom', $dto->metadata);
        $this->assertContains('Test Fandom', $dto->metadata['Fandom']);

        $this->assertArrayHasKey('Relationship', $dto->metadata);
        $this->assertContains('Character A/Character B', $dto->metadata['Relationship']);

        $this->assertArrayHasKey('Character', $dto->metadata);
        $this->assertContains('Character A', $dto->metadata['Character']);
        $this->assertContains('Character B', $dto->metadata['Character']);

        $this->assertArrayHasKey('Tag', $dto->metadata);
        $this->assertContains('Fluff', $dto->metadata['Tag']);
        $this->assertContains('Happy Ending', $dto->metadata['Tag']);
    }

    // --- scrape(): ongoing work (chapters "X/?") ---

    public function test_scrape_ongoing_work_chapters_not_complete(): void
    {
        $scraper = $this->makeScraper($this->fixture('ongoing_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/22222');

        $this->assertSame(10, $dto->chapters);
        $this->assertNull($dto->totalChapters);
        $this->assertFalse($dto->isComplete);
    }

    public function test_scrape_ongoing_work_updated_date_not_set_when_status_is_date(): void
    {
        $scraper = $this->makeScraper($this->fixture('ongoing_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/22222');

        // The ongoing fixture has "2024-06-15" in dd.status — should parse as updated date
        $this->assertSame('2024-06-15', $dto->lastUpdatedDate);
    }

    // --- scrape(): multi-author ---

    public function test_scrape_multi_author_returns_all_authors(): void
    {
        $scraper = $this->makeScraper($this->fixture('multi_author'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/33333');

        $this->assertCount(2, $dto->authors);
        $this->assertContains('AuthorOne', $dto->authors);
        $this->assertContains('AuthorTwo', $dto->authors);
    }

    // --- scrape(): series work ---

    public function test_scrape_series_work_series_fields(): void
    {
        $scraper = $this->makeScraper($this->fixture('series_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/44444');

        $this->assertSame('Test Series Name', $dto->seriesName);
        $this->assertSame(2, $dto->placeInSeries);
        $this->assertStringContainsString('archiveofourown.org/series/99999', $dto->seriesUrl ?? '');
    }

    // --- scrape(): minimal work (most optional fields missing) ---

    public function test_scrape_minimal_work_tolerates_missing_fields(): void
    {
        $scraper = $this->makeScraper($this->fixture('minimal_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/55555');

        $this->assertSame('Minimal Work Title', $dto->title);
        $this->assertSame(['MinimalAuthor'], $dto->authors);
        $this->assertNull($dto->summary);
        $this->assertNull($dto->language);
        $this->assertSame(500, $dto->words);
        $this->assertEmpty($dto->metadata);
    }

    // --- URL normalization ---

    public function test_url_is_normalized_to_canonical_form(): void
    {
        $scraper = $this->makeScraper($this->fixture('minimal_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/55555/chapters/99999');

        // sourceUrl should be the canonical works/{id}?view_adult=true URL
        $this->assertStringContainsString('archiveofourown.org/works/55555', $dto->sourceUrl ?? '');
        $this->assertStringNotContainsString('/chapters/', $dto->sourceUrl ?? '');
    }

    // --- HTTP error handling ---

    public function test_scrape_throws_scraping_exception_on_404(): void
    {
        $scraper = $this->makeScraper('Not found', 404);

        $this->expectException(ScrapingException::class);
        $scraper->scrape('https://archiveofourown.org/works/99999');
    }

    public function test_scraping_exception_carries_url(): void
    {
        $scraper = $this->makeScraper('Not found', 404);

        try {
            $scraper->scrape('https://archiveofourown.org/works/99999');
            $this->fail('Expected ScrapingException');
        } catch (ScrapingException $e) {
            $this->assertStringContainsString('archiveofourown.org', $e->getScrapedUrl());
            $this->assertSame(404, $e->getHttpStatus());
        }
    }

    // --- DTO guarantees ---

    public function test_scrape_result_is_scraped_work_dto(): void
    {
        $scraper = $this->makeScraper($this->fixture('complete_work'));
        $dto = $scraper->scrape('https://archiveofourown.org/works/11111');

        $this->assertInstanceOf(ScrapedWorkDto::class, $dto);
    }
}
