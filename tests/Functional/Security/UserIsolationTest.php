<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class UserIsolationTest extends AbstractFunctionalTest
{
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
