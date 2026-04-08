<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Service\SpreadsheetImportService;
use App\Tests\Functional\AbstractFunctionalTest;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Functional tests for SpreadsheetImportService.
 *
 * Tests exercise the public import() method end-to-end using real XLSX files
 * written with PhpSpreadsheet, avoiding tests of private implementation details.
 *
 * Emoji parsing is tested via round-trip: write encoded values to XLSX, import,
 * read back the persisted ReadingEntry fields.
 */
class SpreadsheetImportServiceTest extends AbstractFunctionalTest
{
    private SpreadsheetImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importService = static::getContainer()->get(SpreadsheetImportService::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Builds and saves an XLSX file with a header row and one data row.
     * $rowData is keyed by 0-based column index.
     * Returns the temp file path. Caller must delete it.
     *
     * @param array<int, mixed> $rowData 0-based column index => value
     */
    private function buildXlsxWithRow(array $rowData): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Minimal header row
        $sheet->getCell('A1')->setValue('Completion status');

        // Data row — convert 0-based column index to column letter (Coordinate is 1-based)
        foreach ($rowData as $colIndex => $value) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->getCell($col . '2')->setValue($value);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'compendium_test_import_') . '.xlsx';
        $writer  = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        return $tmpPath;
    }

    // ── Emoji parsing — review stars ─────────────────────────────────────────

    public function test_emoji_parsing_review_stars_three_stars(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        // COL_TITLE = 4, COL_STATUS_NAME = 6, COL_REVIEW_STARS = 9
        $tmpPath = $this->buildXlsxWithRow([
            4 => 'Review Star Test Work',
            6 => $status->getName(),
            9 => '★★★',
        ]);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(1, $summary->entriesCreated);

        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertSame(3, $entries[0]->getReviewStars());
    }

    public function test_emoji_parsing_review_stars_blank_is_null(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        $tmpPath = $this->buildXlsxWithRow([
            4 => 'No Stars Work',
            6 => $status->getName(),
            // COL_REVIEW_STARS intentionally omitted — blank
        ]);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(1, $summary->entriesCreated);

        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->getReviewStars());
    }

    // ── Emoji parsing — spice stars ──────────────────────────────────────────

    public function test_emoji_parsing_spice_stars_two_chilis(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        // COL_SPICE_STARS = 11; two chili emojis (each is U+1F336 + U+FE0F variation selector)
        $tmpPath = $this->buildXlsxWithRow([
            4  => 'Spicy Work',
            6  => $status->getName(),
            11 => '🌶️🌶️',
        ]);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(1, $summary->entriesCreated);

        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertSame(2, $entries[0]->getSpiceStars());
    }

    public function test_emoji_parsing_spice_stars_no_spice_symbol(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        $tmpPath = $this->buildXlsxWithRow([
            4  => 'No Spice Work',
            6  => $status->getName(),
            11 => '🚫',
        ]);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(1, $summary->entriesCreated);

        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertSame(0, $entries[0]->getSpiceStars());
    }

    public function test_emoji_parsing_spice_stars_blank_is_null(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        $tmpPath = $this->buildXlsxWithRow([
            4 => 'Unrated Spice Work',
            6 => $status->getName(),
            // COL_SPICE_STARS intentionally omitted — blank
        ]);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(1, $summary->entriesCreated);

        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->getSpiceStars());
    }

    // ── Status resolution ─────────────────────────────────────────────────────

    public function test_row_with_unknown_status_is_skipped_and_other_rows_import(): void
    {
        $user = $this->createUser();
        $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        // Build an XLSX with two data rows: row 2 has an unknown status, row 3 is valid
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->getCell('A1')->setValue('Completion status');

        // Row 2 — unknown status (E=title col 5, G=status col 7)
        $sheet->getCell('E2')->setValue('Unknown Status Work');
        $sheet->getCell('G2')->setValue('NonExistentStatus');

        // Row 3 — valid status
        $sheet->getCell('E3')->setValue('Valid Work');
        $sheet->getCell('G3')->setValue('Reading');

        $tmpPath = tempnam(sys_get_temp_dir(), 'compendium_test_import_') . '.xlsx';
        $writer  = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        try {
            $summary = $this->importService->import($user, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        // One row skipped, one entry created
        $this->assertSame(1, $summary->rowsSkipped);
        $this->assertSame(1, $summary->entriesCreated);
        $this->assertCount(1, $summary->errors);
        $this->assertSame(2, $summary->errors[0]->rowNumber);
        $this->assertStringContainsString('NonExistentStatus', $summary->errors[0]->message);

        // The valid row's entry must be in the database
        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $user]);
        $this->assertCount(1, $entries);
        $this->assertSame('Valid Work', $entries[0]->getWork()->getTitle());
    }
}
