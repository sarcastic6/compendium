<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ReadingEntry;
use App\Tests\Functional\AbstractFunctionalTest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class DataControllerImportTest extends AbstractFunctionalTest
{
    /**
     * Builds a minimal valid Familiar Format XLSX with one data row and returns its
     * file path. The caller is responsible for deleting the file.
     *
     * @param string $statusName The status name to write in column G
     */
    private function buildMinimalXlsx(string $statusName = 'Reading'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Header row (row 1)
        $sheet->getCell('A1')->setValue('Completion status');

        // Data row (row 2)
        // COL_TITLE = 4 (0-based) → column E
        // COL_STATUS_NAME = 6 (0-based) → column G
        $sheet->getCell('E2')->setValue('Test Work Title');
        $sheet->getCell('G2')->setValue($statusName);

        $tmpPath = tempnam(sys_get_temp_dir(), 'compendium_test_import_') . '.xlsx';
        $writer  = new XlsxWriter($spreadsheet);
        $writer->save($tmpPath);

        return $tmpPath;
    }

    public function test_unauthenticated_post_to_import_redirects_to_login(): void
    {
        $this->client->request('POST', '/data/import', []);

        $this->assertResponseRedirects('/login');
    }

    public function test_unauthenticated_get_to_import_redirects_to_login(): void
    {
        $this->client->request('GET', '/data/import');

        $this->assertResponseRedirects('/login');
    }

    public function test_user_cannot_access_other_users_imported_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $this->createStatus('Reading');
        $this->createMetadataType('Relationships');

        // Alice imports one entry
        $xlsxPath = $this->buildMinimalXlsx('Reading');

        try {
            $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
            $this->client->followRedirect();

            $crawler    = $this->client->request('GET', '/data/import');
            $csrfToken  = $crawler->filter('input[name="_token"]')->attr('value');

            $this->client->request('POST', '/data/import', [
                '_token' => $csrfToken,
            ], [
                'import_file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $xlsxPath,
                    'import.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
            ]);
        } finally {
            @unlink($xlsxPath);
        }

        $this->assertResponseIsSuccessful();

        // Alice must have exactly 1 reading entry
        $aliceEntries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $alice]);
        $this->assertCount(1, $aliceEntries);

        // Bob must have zero reading entries — Alice's import must not bleed across users
        $bobEntries = $this->em->getRepository(ReadingEntry::class)->findBy(['user' => $bob]);
        $this->assertCount(0, $bobEntries);
    }
}
