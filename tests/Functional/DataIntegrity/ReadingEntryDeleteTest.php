<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class ReadingEntryDeleteTest extends AbstractFunctionalTest
{
    public function test_delete_entry_removes_from_database(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'To Delete');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Visit the show page to get a valid CSRF token from the rendered delete form
        $crawler = $this->client->request('GET', '/reading-entries/' . $entryId);
        $this->assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('#deleteForm input[name="_token"]')->attr('value');

        $this->client->request('POST', '/reading-entries/' . $entryId . '/delete', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/reading-entries');

        $this->em->clear();
        $deleted = $this->em->find(ReadingEntry::class, $entryId);
        $this->assertNull($deleted);
    }

    public function test_delete_requires_csrf_token(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'CSRF Test Book');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // POST with an invalid CSRF token — entry must not be deleted
        $this->client->request('POST', '/reading-entries/' . $entryId . '/delete', [
            '_token' => 'invalid-token',
        ]);

        $this->em->clear();
        $stillExists = $this->em->find(ReadingEntry::class, $entryId);
        $this->assertNotNull($stillExists);
    }

    public function test_bulk_delete_removes_only_selected(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'Delete Me');
        $workB = new Work(WorkType::Book, 'Keep Me');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();
        $entryAId = $entryA->getId();
        $entryBId = $entryB->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Visit the list page to get a valid bulk CSRF token from the rendered form
        $crawler = $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('#bulk-form input[name="_token"]')->attr('value');

        $this->client->request('POST', '/reading-entries/bulk/delete', [
            '_token' => $csrfToken,
            'ids' => [$entryAId],
        ]);

        $this->assertResponseRedirects('/reading-entries');

        $this->em->clear();
        $this->assertNull($this->em->find(ReadingEntry::class, $entryAId));
        $this->assertNotNull($this->em->find(ReadingEntry::class, $entryBId));
    }

    public function test_bulk_status_update_changes_selected(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $statusA = $this->createStatus('Reading');
        $statusB = $this->createStatus('Completed', true);

        $workA = new Work(WorkType::Book, 'Change This');
        $workB = new Work(WorkType::Book, 'Leave This');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $statusA);
        $entryB = new ReadingEntry($alice, $workB, $statusA);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();
        $entryAId = $entryA->getId();
        $entryBId = $entryB->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Visit the list page to get a valid bulk CSRF token
        $crawler = $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('#bulk-form input[name="_token"]')->attr('value');

        $this->client->request('POST', '/reading-entries/bulk/status', [
            '_token' => $csrfToken,
            'ids' => [$entryAId],
            'status_id' => $statusB->getId(),
        ]);

        $this->assertResponseRedirects('/reading-entries');

        $this->em->clear();
        $changedEntry = $this->em->find(ReadingEntry::class, $entryAId);
        $unchangedEntry = $this->em->find(ReadingEntry::class, $entryBId);

        $this->assertSame($statusB->getId(), $changedEntry->getStatus()->getId());
        $this->assertSame($statusA->getId(), $unchangedEntry->getStatus()->getId());
    }
}
