<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class UserIsolationTest extends AbstractFunctionalTest
{
    public function test_user_cannot_edit_other_users_entry(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Bob Book');
        $this->em->persist($work);
        $this->em->flush();

        // Entry belongs to Bob
        $entry = new ReadingEntry($bob, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        // Alice tries to access Bob's edit page
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/' . $entryId . '/edit');
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_user_cannot_delete_other_users_entry(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Bob Book');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($bob, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        // Create an entry for Alice BEFORE login (avoids entity detachment after HTTP requests)
        $aliceWork = new Work(WorkType::Book, 'Alice Work');
        $this->em->persist($aliceWork);
        $this->em->flush();
        $aliceEntry = new ReadingEntry($alice, $aliceWork, $status);
        $this->em->persist($aliceEntry);
        $this->em->flush();
        $aliceEntryId = $aliceEntry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // The controller returns 404 before CSRF check: findByIdForUser() returns null
        // for Bob's entry when Alice is logged in. Token value doesn't matter here.
        $this->client->request('POST', '/reading-entries/' . $entryId . '/delete', [
            '_token' => 'any-token-value',
        ]);

        $this->assertResponseStatusCodeSame(404);

        // Bob's entry must still exist
        $this->em->clear();
        $this->assertNotNull($this->em->find(ReadingEntry::class, $entryId));
    }

    public function test_bulk_operations_ignore_other_users_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');
        $statusDone = $this->createStatus('Completed', true);

        $aliceWork = new Work(WorkType::Book, 'Alice Work');
        $bobWork = new Work(WorkType::Book, 'Bob Work');
        $this->em->persist($aliceWork);
        $this->em->persist($bobWork);
        $this->em->flush();

        $aliceEntry = new ReadingEntry($alice, $aliceWork, $status);
        $bobEntry = new ReadingEntry($bob, $bobWork, $status);
        $this->em->persist($aliceEntry);
        $this->em->persist($bobEntry);
        $this->em->flush();
        $bobEntryId = $bobEntry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Visit the list page (which renders Alice's entry) to get a valid bulk CSRF token
        $crawler = $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('#bulk-form input[name="_token"]')->attr('value');

        // Alice submits Bob's entry ID for bulk status change
        $this->client->request('POST', '/reading-entries/bulk/status', [
            '_token' => $csrfToken,
            'ids' => [$bobEntryId],
            'status_id' => $statusDone->getId(),
        ]);

        // Bob's entry status must remain unchanged — service filters by user
        $this->em->clear();
        $unchanged = $this->em->find(ReadingEntry::class, $bobEntryId);
        $this->assertSame($status->getId(), $unchanged->getStatus()->getId());
    }

    public function test_reading_list_shows_only_own_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $aliceWork = new Work(WorkType::Book, 'Alice Book');
        $bobWork = new Work(WorkType::Book, 'Bob Book');
        $this->em->persist($aliceWork);
        $this->em->persist($bobWork);
        $this->em->flush();

        $aliceEntry = new ReadingEntry($alice, $aliceWork, $status);
        $bobEntry = new ReadingEntry($bob, $bobWork, $status);
        $this->em->persist($aliceEntry);
        $this->em->persist($bobEntry);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Alice Book', $content);
        $this->assertStringNotContainsString('Bob Book', $content);
    }

    public function test_user_cannot_create_entry_as_another_user(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Shared Book');
        $this->em->persist($work);
        $this->em->flush();

        // Alice logs in and creates an entry for the work
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/new/' . $work->getId());
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        // The entry should be owned by Alice, not Bob
        $entry = $this->em->getRepository(ReadingEntry::class)->findOneBy(['work' => $work]);
        $this->assertNotNull($entry);
        $this->assertSame($alice->getId(), $entry->getUser()->getId());
        $this->assertNotSame($bob->getId(), $entry->getUser()->getId());
    }
}
