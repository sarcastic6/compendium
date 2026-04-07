<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\ReadingEntry;

interface ExportFormatInterface
{
    /**
     * Returns the column header labels for this export format.
     *
     * @return string[]
     */
    public function getHeaders(): array;

    /**
     * Builds the data rows for the given reading entries.
     * Each inner array represents one row; values correspond to the headers
     * returned by getHeaders(). NULL values produce blank cells.
     *
     * @param ReadingEntry[] $entries
     * @return array<int, array<int, mixed>>
     */
    public function buildRows(array $entries): array;
}
