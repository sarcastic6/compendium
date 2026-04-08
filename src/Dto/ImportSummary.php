<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Summary returned by SpreadsheetImportService after processing an import file.
 * Passed to the import result template for display.
 */
final class ImportSummary
{
    public int $entriesCreated = 0;
    public int $worksCreated = 0;
    public int $worksReused = 0;
    public int $worksQueuedForScraping = 0;
    public int $rowsSkipped = 0;

    /** @var ImportRowError[] */
    public array $errors = [];
}
