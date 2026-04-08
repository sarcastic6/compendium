<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a skipped row from a spreadsheet import.
 * Returned as part of ImportSummary so the result page can show a per-row error table.
 */
final class ImportRowError
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $message,
    ) {
    }
}
