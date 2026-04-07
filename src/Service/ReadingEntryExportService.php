<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Export\ExportFormatInterface;
use App\Repository\ReadingEntryRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ReadingEntryExportService
{
    public function __construct(
        private readonly ReadingEntryRepository $readingEntryRepository,
    ) {
    }

    /**
     * Fetches all reading entries for the user and builds a Spreadsheet
     * using the given format's headers and row-building logic.
     */
    public function buildSpreadsheet(User $user, ExportFormatInterface $format): Spreadsheet
    {
        $entries = $this->readingEntryRepository->findAllForUserExport($user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($format->getHeaders(), null, 'A1');

        $rows = $format->buildRows($entries);

        if ($rows !== []) {
            // Pass true for strict null comparison — without it, PHP's loose == means
            // 0 == null evaluates to true and spice value 0 (no spice) is written as
            // a blank cell, indistinguishable from a null (un-entered) spice value.
            $sheet->fromArray($rows, null, 'A2', true);
        }

        return $spreadsheet;
    }
}
