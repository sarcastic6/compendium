<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ReadingEntry;
use App\Entity\Status;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DataControllerTest extends AbstractFunctionalTest
{
    public function test_unauthenticated_user_is_redirected_from_export(): void
    {
        $this->client->request('GET', '/data/export/data-dump');

        $this->assertResponseRedirects('/login');
    }

    public function test_user_export_contains_only_own_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $aliceWork = new Work(WorkType::Book, 'Alice Book');
        $bobWork   = new Work(WorkType::Book, 'Bob Book');
        $this->em->persist($aliceWork);
        $this->em->persist($bobWork);
        $this->em->flush();

        $this->em->persist(new ReadingEntry($alice, $aliceWork, $status));
        $this->em->persist(new ReadingEntry($bob, $bobWork, $status));
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/data/export/data-dump');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $titles = $this->extractTitleColumn($this->client->getResponse()->getContent());
        $this->assertContains('Alice Book', $titles);
        $this->assertNotContains('Bob Book', $titles);
    }

    public function test_export_includes_entries_for_soft_deleted_works(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Deleted Work');
        $this->em->persist($work);
        $this->em->flush();

        $this->em->persist(new ReadingEntry($user, $work, $status));
        $this->em->flush();

        // Soft-delete the work after the entry has been recorded
        $work->softDelete();
        $this->em->flush();

        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/data/export/data-dump');

        $this->assertResponseIsSuccessful();

        $titles = $this->extractTitleColumn($this->client->getResponse()->getContent());
        $this->assertContains('Deleted Work', $titles);
    }

    public function test_familiar_export_contains_only_own_entries(): void
    {
        $alice  = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob    = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $aliceWork = new Work(WorkType::Book, 'Alice Familiar Book');
        $bobWork   = new Work(WorkType::Book, 'Bob Familiar Book');
        $this->em->persist($aliceWork);
        $this->em->persist($bobWork);
        $this->em->flush();

        $this->em->persist(new ReadingEntry($alice, $aliceWork, $status));
        $this->em->persist(new ReadingEntry($bob, $bobWork, $status));
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/data/export/familiar');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        // Column E is Title in the Familiar format
        $titles = array_filter(
            array_map('strval', $this->extractColumn($this->client->getResponse()->getContent(), 'Title')),
            static fn (string $v) => $v !== '',
        );
        $this->assertContains('Alice Familiar Book', $titles);
        $this->assertNotContains('Bob Familiar Book', $titles);
    }

    public function test_familiar_export_completion_status_column(): void
    {
        $user = $this->createUser();

        // Four statuses covering every branch of the completion-status logic
        $completed = new Status('Completed', true, true, false);
        $reading   = new Status('Reading', true, false, true);
        $dnf       = new Status('DNF', true, false, false);
        $tbr       = new Status('TBR', false, false, false);
        $this->em->persist($completed);
        $this->em->persist($reading);
        $this->em->persist($dnf);
        $this->em->persist($tbr);

        $works = [];
        foreach (['Completed Work', 'Reading Work', 'DNF Work', 'TBR Work'] as $title) {
            $w = new Work(WorkType::Book, $title);
            $this->em->persist($w);
            $works[$title] = $w;
        }
        $this->em->flush();

        $this->em->persist(new ReadingEntry($user, $works['Completed Work'], $completed));
        $this->em->persist(new ReadingEntry($user, $works['Reading Work'], $reading));
        $this->em->persist(new ReadingEntry($user, $works['DNF Work'], $dnf));
        $this->em->persist(new ReadingEntry($user, $works['TBR Work'], $tbr));
        $this->em->flush();

        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/data/export/familiar');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();

        $completionByTitle = array_combine(
            $this->extractColumn($content, 'Title'),
            $this->extractColumn($content, 'Completion status'),
        );

        $this->assertSame('Complete',  $completionByTitle['Completed Work']);
        $this->assertSame('WIP',       $completionByTitle['Reading Work']);
        $this->assertSame('Abandoned', $completionByTitle['DNF Work']);
        $this->assertNull($completionByTitle['TBR Work']); // has_been_started = false → blank
    }

    public function test_export_distinguishes_spice_zero_from_null(): void
    {
        $user   = $this->createUser();
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'No Spice Work');
        $workB = new Work(WorkType::Book, 'Unrated Spice Work');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryNoSpice = new ReadingEntry($user, $workA, $status);
        $entryNoSpice->setSpiceStars(0);

        $entryUnrated = new ReadingEntry($user, $workB, $status);
        // spiceStars intentionally left null

        $this->em->persist($entryNoSpice);
        $this->em->persist($entryUnrated);
        $this->em->flush();

        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/data/export/data-dump');

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();

        // Spice column values keyed by title for clarity
        $spiceByTitle = array_combine(
            $this->extractColumn($content, 'Title'),
            $this->extractColumn($content, 'Spice'),
        );

        // spiceStars = 0 must produce a 0 cell, not a blank
        $this->assertSame(0, $spiceByTitle['No Spice Work']);

        // spiceStars = null must produce a blank cell, not a 0
        $this->assertNull($spiceByTitle['Unrated Spice Work']);
    }

    /**
     * Reads the XLSX response body and returns all non-null values from the
     * Title column (column A), skipping the header row.
     *
     * @return string[]
     */
    private function extractTitleColumn(string $content): array
    {
        return array_values(array_filter(
            array_map('strval', $this->extractColumn($content, 'Title')),
            static fn (string $v) => $v !== '',
        ));
    }

    /**
     * Reads the XLSX response body and returns all data-row values (including nulls)
     * from the column whose header matches $headerName, skipping the header row.
     *
     * @return array<int, mixed>
     */
    private function extractColumn(string $content, string $headerName): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'compendium_test_export_');
        file_put_contents($tmpFile, $content);

        try {
            $spreadsheet = IOFactory::load($tmpFile);
            $sheet       = $spreadsheet->getActiveSheet();
            $highestCol  = $sheet->getHighestColumn();
            $highestRow  = $sheet->getHighestRow();

            // Locate the column letter whose row-1 header matches the requested name
            $colLetter = null;
            foreach ($sheet->getColumnIterator('A', $highestCol) as $col) {
                $letter = $col->getColumnIndex();
                if ($sheet->getCell($letter . '1')->getValue() === $headerName) {
                    $colLetter = $letter;
                    break;
                }
            }

            if ($colLetter === null) {
                return [];
            }

            $values = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $values[] = $sheet->getCell($colLetter . $row)->getValue();
            }

            return $values;
        } finally {
            unlink($tmpFile);
        }
    }
}
