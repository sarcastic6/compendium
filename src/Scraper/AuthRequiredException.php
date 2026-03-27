<?php

declare(strict_types=1);

namespace App\Scraper;

/**
 * Thrown when an AO3 work requires authentication and the scraper either has
 * auth disabled or failed to log in.
 *
 * Extends ScrapingException so callers that only catch the parent still handle
 * it gracefully, but controllers can catch it first to show a more specific message.
 */
class AuthRequiredException extends ScrapingException
{
    public function __construct(string $scrapedUrl, string $message = '')
    {
        parent::__construct($scrapedUrl, $message, 403);
    }
}
