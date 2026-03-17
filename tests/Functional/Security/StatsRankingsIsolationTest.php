<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Metadata;
use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Service\StatisticsService;
use App\Tests\Functional\AbstractFunctionalTest;
use DateTimeImmutable;

/**
 * Verifies that the rankings endpoint enforces user isolation and access control.
 *
 * The rankings page joins through shared global tables (works, metadata).
 * These tests confirm that counts reflect only the requesting user's entries.
 */
class StatsRankingsIsolationTest extends AbstractFunctionalTest
{
    public function test_unauthenticated_access_to_rankings_redirects_to_login(): void
    {
        $this->createMetadataType('Author');

        $this->client->request('GET', '/stats/rankings/Author');

        $this->assertResponseRedirects('/login');
    }

    public function test_rankings_404_for_nonexistent_metadata_type(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/stats/rankings/NonExistentType');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_rankings_counts_only_own_entries_not_other_users(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');
        $status = $this->createStatus('Completed', true);
        $authorType = $this->createMetadataType('Author');

        $work = new Work(WorkType::Fanfiction, 'Shared Work');
        $this->em->persist($work);

        $author = new Metadata('Jane Doe', $authorType);
        $this->em->persist($author);
        $this->em->flush();

        $work->addMetadata($author);
        $this->em->flush();

        // Alice reads it 2 times; Bob reads it 7 times
        for ($i = 0; $i < 2; $i++) {
            $entry = new ReadingEntry($alice, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2025-03-01'));
            $this->em->persist($entry);
        }
        for ($i = 0; $i < 7; $i++) {
            $entry = new ReadingEntry($bob, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2025-03-01'));
            $this->em->persist($entry);
        }
        $this->em->flush();

        /** @var StatisticsService $statsService */
        $statsService = static::getContainer()->get(StatisticsService::class);

        $aliceRankings = $statsService->getTopMetadata($alice, 'Author', 50, null);

        // Alice's count for 'Jane Doe' must be 2, not 9 (2+7)
        $this->assertCount(1, $aliceRankings);
        $this->assertSame('Jane Doe', $aliceRankings[0]['name']);
        $this->assertSame(2, $aliceRankings[0]['count']);
    }

    public function test_available_ranking_types_excludes_types_with_no_user_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $status = $this->createStatus('Completed', true);
        $authorType = $this->createMetadataType('Author');
        $fandomType = $this->createMetadataType('Fandom');

        $work = new Work(WorkType::Fanfiction, 'Alice Work');
        $this->em->persist($work);

        // Only attach an Author — no Fandom metadata
        $author = new Metadata('Some Author', $authorType);
        $this->em->persist($author);
        $this->em->flush();

        $work->addMetadata($author);
        $this->em->flush();

        $entry = new ReadingEntry($alice, $work, $status);
        $entry->setDateFinished(new DateTimeImmutable('2025-01-01'));
        $this->em->persist($entry);
        $this->em->flush();

        /** @var StatisticsService $statsService */
        $statsService = static::getContainer()->get(StatisticsService::class);

        $types = $statsService->getAvailableRankingTypes($alice, null);

        // Alice has Author entries but no Fandom entries
        $this->assertContains('Author', $types);
        $this->assertNotContains('Fandom', $types);
    }

    public function test_year_filter_applied_to_rankings(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $status = $this->createStatus('Completed', true);
        $authorType = $this->createMetadataType('Author');

        $work = new Work(WorkType::Book, 'Time-tested Book');
        $this->em->persist($work);

        $author = new Metadata('Time Author', $authorType);
        $this->em->persist($author);
        $this->em->flush();

        $work->addMetadata($author);
        $this->em->flush();

        // 3 entries finished in 2024, 1 entry finished in 2025
        for ($i = 0; $i < 3; $i++) {
            $entry = new ReadingEntry($alice, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2024-06-01'));
            $this->em->persist($entry);
        }
        $entry2025 = new ReadingEntry($alice, $work, $status);
        $entry2025->setDateFinished(new DateTimeImmutable('2025-01-15'));
        $this->em->persist($entry2025);
        $this->em->flush();

        /** @var StatisticsService $statsService */
        $statsService = static::getContainer()->get(StatisticsService::class);

        $rankings2024 = $statsService->getTopMetadata($alice, 'Author', 50, 2024);
        $rankings2025 = $statsService->getTopMetadata($alice, 'Author', 50, 2025);

        $this->assertCount(1, $rankings2024);
        $this->assertSame(3, $rankings2024[0]['count']);

        $this->assertCount(1, $rankings2025);
        $this->assertSame(1, $rankings2025[0]['count']);
    }
}
