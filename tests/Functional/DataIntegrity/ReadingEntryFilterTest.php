<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Tests\Functional\AbstractFunctionalTest;
use DateTimeImmutable;

class ReadingEntryFilterTest extends AbstractFunctionalTest
{
    public function test_filter_by_status_returns_matching_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $statusReading = $this->createStatus('Reading');
        $statusDone = $this->createStatus('Completed', true);

        $workA = new Work(WorkType::Book, 'Reading Book');
        $workB = new Work(WorkType::Book, 'Done Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $statusReading);
        $entryB = new ReadingEntry($alice, $workB, $statusDone);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries?status=' . $statusReading->getId());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Reading Book', $content);
        $this->assertStringNotContainsString('Done Book', $content);
    }

    public function test_filter_by_title_search_returns_matching_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'Dragon Tales');
        $workB = new Work(WorkType::Book, 'Unicorn Stories');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries?q=Dragon');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Dragon Tales', $content);
        $this->assertStringNotContainsString('Unicorn Stories', $content);
    }

    public function test_filter_by_date_range_returns_matching_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Completed', true);

        $workA = new Work(WorkType::Book, 'January Book');
        $workB = new Work(WorkType::Book, 'March Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryA->setDateFinished(new DateTimeImmutable('2025-01-15'));
        $entryB = new ReadingEntry($alice, $workB, $status);
        $entryB->setDateFinished(new DateTimeImmutable('2025-03-20'));
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries?dateFrom=2025-01-01&dateTo=2025-02-01');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('January Book', $content);
        $this->assertStringNotContainsString('March Book', $content);
    }

    public function test_empty_filter_returns_all_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'Alpha Book');
        $workB = new Work(WorkType::Book, 'Beta Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Alpha Book', $content);
        $this->assertStringContainsString('Beta Book', $content);
    }
}
