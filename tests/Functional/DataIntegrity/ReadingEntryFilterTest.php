<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\Metadata;
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

    public function test_statusname_param_redirects_to_status_id_filter(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $statusDone = $this->createStatus('Completed', true, true);
        $statusReading = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'Finished Book');
        $workB = new Work(WorkType::Book, 'Reading Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $statusDone);
        $entryB = new ReadingEntry($alice, $workB, $statusReading);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // statusName should redirect to ?status=<id>
        $this->client->request('GET', '/reading-entries?statusName=Completed');
        $this->assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('status=' . $statusDone->getId(), $location);
        $this->assertStringNotContainsString('statusName', $location);

        // Following the redirect should show only the completed entry
        $this->client->followRedirect();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Finished Book', $content);
        $this->assertStringNotContainsString('Reading Book', $content);
    }

    public function test_filter_by_metadata_uses_partial_match(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');
        $characterType = $this->createMetadataType('Character');

        $kimMeta = new Metadata('Kim Possible', $characterType);
        $ronMeta = new Metadata('Ron Stoppable', $characterType);
        $this->em->persist($kimMeta);
        $this->em->persist($ronMeta);

        $workA = new Work(WorkType::Fanfiction, 'Team Possible Adventure');
        $workA->addMetadata($kimMeta);
        $workB = new Work(WorkType::Fanfiction, 'Ron Solo Story');
        $workB->addMetadata($ronMeta);
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

        // Partial match on "Kim" should match "Kim Possible" but not "Ron Stoppable"
        $this->client->request('GET', '/reading-entries?' . http_build_query(['metadata' => ['Character' => 'Kim']]));
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Team Possible Adventure', $content);
        $this->assertStringNotContainsString('Ron Solo Story', $content);
    }

    public function test_spice_zero_filters_exact_no_spice_only(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'No Spice Book');
        $workB = new Work(WorkType::Book, 'Mild Spice Book');
        $workC = new Work(WorkType::Book, 'Hot Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->persist($workC);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryA->setSpiceStars(0);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $entryB->setSpiceStars(2);
        $entryC = new ReadingEntry($alice, $workC, $status);
        $entryC->setSpiceStars(5);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->persist($entryC);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // spice=0 must be exact (no-spice only), not minimum
        $this->client->request('GET', '/reading-entries?spice=0');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('No Spice Book', $content);
        $this->assertStringNotContainsString('Mild Spice Book', $content);
        $this->assertStringNotContainsString('Hot Book', $content);
    }

    public function test_spice_minimum_filter_returns_entries_at_or_above(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'No Spice Book');
        $workB = new Work(WorkType::Book, 'Low Spice Book');
        $workC = new Work(WorkType::Book, 'High Spice Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->persist($workC);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryA->setSpiceStars(0);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $entryB->setSpiceStars(2);
        $entryC = new ReadingEntry($alice, $workC, $status);
        $entryC->setSpiceStars(4);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->persist($entryC);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // spice=2 should show entries with spice >= 2, not just exactly 2
        $this->client->request('GET', '/reading-entries?spice=2');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('No Spice Book', $content);
        $this->assertStringContainsString('Low Spice Book', $content);
        $this->assertStringContainsString('High Spice Book', $content);
    }

    public function test_spice_exact_filter_returns_only_that_value(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Reading');

        $workA = new Work(WorkType::Book, 'Low Spice Book');
        $workB = new Work(WorkType::Book, 'Medium Spice Book');
        $workC = new Work(WorkType::Book, 'High Spice Book');
        $this->em->persist($workA);
        $this->em->persist($workB);
        $this->em->persist($workC);
        $this->em->flush();

        $entryA = new ReadingEntry($alice, $workA, $status);
        $entryA->setSpiceStars(1);
        $entryB = new ReadingEntry($alice, $workB, $status);
        $entryB->setSpiceStars(3);
        $entryC = new ReadingEntry($alice, $workC, $status);
        $entryC->setSpiceStars(5);
        $this->em->persist($entryA);
        $this->em->persist($entryB);
        $this->em->persist($entryC);
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // spiceExact=3 must return only entries with exactly spice=3
        $this->client->request('GET', '/reading-entries?spiceExact=3');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('Low Spice Book', $content);
        $this->assertStringContainsString('Medium Spice Book', $content);
        $this->assertStringNotContainsString('High Spice Book', $content);
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
