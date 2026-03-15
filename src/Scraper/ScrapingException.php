<?php

declare(strict_types=1);

namespace App\Scraper;

/**
 * Thrown when a scraper cannot retrieve or parse a work's metadata.
 */
class ScrapingException extends \RuntimeException
{
    public function __construct(
        private readonly string $scrapedUrl,
        string $message = '',
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getScrapedUrl(): string
    {
        return $this->scrapedUrl;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
