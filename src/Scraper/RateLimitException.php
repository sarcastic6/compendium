<?php

declare(strict_types=1);

namespace App\Scraper;

/**
 * Thrown when a scraper receives a rate-limit or transient infrastructure response.
 *
 * This is NOT a subclass of ScrapingException. A rate limit is a signal to retry later,
 * not a permanent scrape failure. Keeping them separate prevents catch blocks for
 * permanent failures from accidentally swallowing retry signals.
 *
 * Triggered by: HTTP 429, 503 (rate limit), 502, 504 (transient Cloudflare/infra errors).
 */
class RateLimitException extends \RuntimeException
{
    public function __construct(
        private readonly string $url,
        private readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct(sprintf('AO3 rate limit hit for URL: %s', $url));
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Seconds to wait as instructed by the server via Retry-After header,
     * or null if no header was present or the value exceeded the cap (120 s).
     * When null, the caller should apply its own backoff strategy.
     */
    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
