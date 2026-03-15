<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;

class SoftDeleteTest extends AbstractFunctionalTest
{
    public function test_soft_deleted_work_is_excluded_from_work_select(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');

        $work = new Work(WorkType::Book, 'Deleted Book');
        $this->em->persist($work);
        $this->em->flush();

        $work->softDelete();
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // The deleted work should not appear in search results (query is echoed in the input, but no result items)
        $this->client->request('GET', '/work/select?q=Deleted+Book');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('No works found', $content);
    }

    public function test_reading_entry_for_soft_deleted_work_still_visible_on_list(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $work = new Work(WorkType::Book, 'To Be Deleted');
        $this->em->persist($work);
        $this->em->flush();

        // Create an entry before soft-deleting
        $entry = new ReadingEntry($alice, $work, $status);
        $this->em->persist($entry);
        $this->em->flush();

        // Soft-delete the work
        $work->softDelete();
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        // The entry should still appear (with the deleted-work indicator)
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('entry-deleted-work', $content);
    }

    public function test_soft_deleted_work_cannot_have_new_entry_created(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');

        $work = new Work(WorkType::Book, 'Deleted Work');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $work->softDelete();
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Attempting to create an entry for a soft-deleted work should result in 404
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->assertResponseStatusCodeSame(404);
    }
}
