<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class ReadingEntryPinTest extends AbstractFunctionalTest
{
    public function test_toggle_pin_pins_an_unpinned_entry(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Pin Me');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Get the CSRF token from the rendered list page (data-pin-token attribute on dropdown button)
        $crawler = $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();
        $csrfToken = $crawler->filter('[data-pin-url$="/' . $entryId . '/pin"]')->first()->attr('data-pin-token');

        $this->client->request('POST', '/reading-entries/' . $entryId . '/pin', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $this->assertTrue($this->em->find(ReadingEntry::class, $entryId)->isPinned());
    }

    public function test_toggle_pin_unpins_a_pinned_entry(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Unpin Me');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $entry->setPinned(true);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Entry is pinned so it appears in both sections; get token from the main list button
        $crawler = $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();
        $csrfToken = $crawler->filter('[data-pin-url$="/' . $entryId . '/pin"]')->first()->attr('data-pin-token');

        $this->client->request('POST', '/reading-entries/' . $entryId . '/pin', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $this->assertFalse($this->em->find(ReadingEntry::class, $entryId)->isPinned());
    }

    public function test_toggle_pin_requires_valid_csrf_token(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'CSRF Pin Test');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/reading-entries/' . $entryId . '/pin', [
            '_token' => 'invalid-token',
        ]);

        // Must redirect with flash error, not modify the entry
        $this->assertResponseRedirects();
        $this->em->clear();
        $this->assertFalse($this->em->find(ReadingEntry::class, $entryId)->isPinned());
    }
}
