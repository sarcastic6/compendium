<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class ReadingEntryCreationTest extends AbstractFunctionalTest
{
    public function test_entry_with_minimal_fields_persists_with_correct_user(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Test Book');
        $this->em->persist($work);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/new/' . $work->getId());
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->assertResponseRedirects('/reading-entries');

        $this->em->clear();
        $entry = $this->em->getRepository(ReadingEntry::class)->findOneBy(['work' => $work->getId()]);
        $this->assertNotNull($entry);
        $this->assertSame($alice->getId(), $entry->getUser()->getId());
        $this->assertSame($status->getId(), $entry->getStatus()->getId());
    }

    public function test_multiple_entries_allowed_for_same_work(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Completed', true);

        $work = new Work(WorkType::Book, 'Re-readable Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // First entry
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, ['reading_entry_form[status]' => $status->getId()]);

        // Second entry (re-read)
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, ['reading_entry_form[status]' => $status->getId()]);

        $this->em->clear();
        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['work' => $workId]);
        $this->assertCount(2, $entries);
    }

    public function test_review_stars_out_of_range_rejected(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Test Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // The form uses ChoiceType 1-5, so value 6 is rejected by Symfony form validation
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $crawler = $this->client->getCrawler();
        $csrfToken = $crawler->filter('input[name="reading_entry_form[_token]"]')->attr('value');

        $this->client->request('POST', '/reading-entries/new/' . $workId, [
            'reading_entry_form' => [
                'status' => $status->getId(),
                'reviewStars' => 6,
                '_token' => $csrfToken,
            ],
        ]);

        $this->em->clear();
        $entries = $this->em->getRepository(ReadingEntry::class)->findBy(['work' => $workId]);
        $this->assertCount(0, $entries);
    }

    public function test_user_id_is_set_from_session_not_form(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Test Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        // Log in as Alice
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->em->clear();
        $entry = $this->em->getRepository(ReadingEntry::class)->findOneBy(['work' => $workId]);
        $this->assertNotNull($entry);
        // Must be Alice's ID regardless of any form manipulation
        $this->assertSame($alice->getId(), $entry->getUser()->getId());
        $this->assertNotSame($bob->getId(), $entry->getUser()->getId());
    }
}
