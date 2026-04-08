<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Messenger message to scrape a Work's metadata in the background.
 *
 * Dispatched during spreadsheet import for each new Work that has a source URL.
 * The handler calls Ao3Scraper::scrape(), merges the result into the Work via
 * WorkService::refreshWork() with $fullReplace = true, then sets scrape_status.
 *
 * $attempt is incremented on each manual requeue (rate-limit / transport error)
 * to drive the exponential backoff formula. It is separate from Messenger's own
 * retry counter, which handles unhandled exceptions via retry_strategy.
 */
final readonly class ScrapeWorkMessage
{
    public function __construct(
        public int $workId,
        public string $url,
        public int $attempt = 0,
    ) {}
}
