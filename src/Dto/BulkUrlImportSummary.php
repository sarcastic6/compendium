<?php

declare(strict_types=1);

namespace App\Dto;

final class BulkUrlImportSummary
{
    public int $worksQueued = 0;

    /**
     * URLs that were skipped because a Work with that link already exists.
     * Keys are canonical URLs, values are the existing work titles.
     *
     * @var array<string, string>
     */
    public array $skippedUrls = [];

    /**
     * Lines that could not be parsed as a supported work URL.
     *
     * @var string[]
     */
    public array $invalidUrls = [];
}
