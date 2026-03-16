<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class ReadingEntryEditTest extends AbstractFunctionalTest
{
    public function test_edit_entry_updates_fields_correctly(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $statusReading = $this->createStatus('Reading');
        $statusDone = $this->createStatus('Completed', true);

        $work = new Work(WorkType::Book, 'Test Book');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $statusReading);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/' . $entryId . '/edit');
        $this->assertResponseIsSuccessful();

        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $statusDone->getId(),
            'reading_entry_form[reviewStars]' => 4,
            'reading_entry_form[comments]' => 'Great read.',
        ]);

        $this->assertResponseRedirects('/reading-entries/' . $entryId);

        $this->em->clear();
        $updated = $this->em->find(ReadingEntry::class, $entryId);
        $this->assertNotNull($updated);
        $this->assertSame($statusDone->getId(), $updated->getStatus()->getId());
        $this->assertSame(4, $updated->getReviewStars());
        $this->assertSame('Great read.', $updated->getComments());
    }

    public function test_edit_entry_validates_star_range(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'Test Book');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Submit an invalid stars value (6 is out of range for ChoiceType 1-5)
        $this->client->request('GET', '/reading-entries/' . $entryId . '/edit');
        $crawler = $this->client->getCrawler();
        $csrfToken = $crawler->filter('input[name="reading_entry_form[_token]"]')->attr('value');

        $this->client->request('POST', '/reading-entries/' . $entryId . '/edit', [
            'reading_entry_form' => [
                'status' => $status->getId(),
                'reviewStars' => 6,
                '_token' => $csrfToken,
            ],
        ]);

        // Should not redirect (form re-rendered; Symfony returns 422 on form validation failure)
        $this->assertResponseStatusCodeSame(422);

        $this->em->clear();
        $unchanged = $this->em->find(ReadingEntry::class, $entryId);
        $this->assertNull($unchanged->getReviewStars());
    }

    public function test_edit_preserves_user_and_work(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $statusA = $this->createStatus('Reading');
        $statusB = $this->createStatus('Completed', true);

        $work = new Work(WorkType::Book, 'Original Work');
        $this->em->persist($work);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $statusA);
        $this->em->persist($entry);
        $this->em->flush();
        $entryId = $entry->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/' . $entryId . '/edit');
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $statusB->getId(),
        ]);

        $this->em->clear();
        $updated = $this->em->find(ReadingEntry::class, $entryId);
        $this->assertNotNull($updated);
        // User must not change
        $this->assertSame($alice->getId(), $updated->getUser()->getId());
        // Work must not change
        $this->assertSame($work->getId(), $updated->getWork()->getId());
    }
}
