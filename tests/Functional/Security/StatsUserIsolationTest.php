<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Metadata;
use App\Entity\ReadingEntry;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Repository\ReadingEntryRepository;
use App\Service\StatisticsService;
use App\Tests\Functional\AbstractFunctionalTest;
use DateTimeImmutable;

/**
 * Verifies that statistics queries are strictly scoped to the requesting user.
 * The critical risk is the getTopMetadata join chain
 * (reading_entries → works → works_metadata → metadata → metadata_types),
 * which must anchor on the user's reading_entries to avoid leaking other
 * users' entry counts when works and metadata are shared across users.
 */
class StatsUserIsolationTest extends AbstractFunctionalTest
{
    public function test_unauthenticated_access_to_stats_redirects_to_login(): void
    {
        $this->client->request('GET', '/stats');

        $this->assertResponseRedirects('/login');
    }

    public function test_dashboard_shows_only_own_entry_count(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');
        $status = $this->createStatus('Reading');

        $aliceWork = new Work(WorkType::Book, 'Alice Work');
        $bobWork = new Work(WorkType::Book, 'Bob Work');
        $this->em->persist($aliceWork);
        $this->em->persist($bobWork);
        $this->em->flush();

        // Alice has 2 entries, Bob has 3
        for ($i = 0; $i < 2; $i++) {
            $this->em->persist(new ReadingEntry($alice, $aliceWork, $status));
        }
        for ($i = 0; $i < 3; $i++) {
            $this->em->persist(new ReadingEntry($bob, $bobWork, $status));
        }
        $this->em->flush();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        /** @var StatisticsService $statsService */
        $statsService = static::getContainer()->get(StatisticsService::class);
        $summary = $statsService->getDashboardSummary($alice, null);

        // Alice has 2 entries, not 5
        $this->assertSame(2, $summary['entryCount']);
    }

    public function test_top_metadata_counts_only_own_entries_not_other_users(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');
        $status = $this->createStatus('Completed', true);
        $authorType = $this->createMetadataType('Author');

        // One shared work with one shared author
        $work = new Work(WorkType::Fanfiction, 'Shared Work');
        $this->em->persist($work);

        $author = new Metadata('Shared Author', $authorType);
        $this->em->persist($author);
        $this->em->flush();

        $work->addMetadata($author);
        $this->em->flush();

        // Alice reads the shared work 3 times; Bob reads it 5 times
        for ($i = 0; $i < 3; $i++) {
            $entry = new ReadingEntry($alice, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2025-06-01'));
            $this->em->persist($entry);
        }
        for ($i = 0; $i < 5; $i++) {
            $entry = new ReadingEntry($bob, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2025-06-01'));
            $this->em->persist($entry);
        }
        $this->em->flush();

        /** @var StatisticsService $statsService */
        $statsService = static::getContainer()->get(StatisticsService::class);

        $aliceTopAuthors = $statsService->getTopMetadata($alice, 'Author', 10, null);

        // Alice's count for 'Shared Author' must be 3, not 8 (not 3+5)
        $this->assertCount(1, $aliceTopAuthors);
        $this->assertSame('Shared Author', $aliceTopAuthors[0]['name']);
        $this->assertSame(3, $aliceTopAuthors[0]['count']);
    }

    public function test_count_by_work_type_counts_only_own_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');
        $status = $this->createStatus('Reading');

        // Shared fanfiction work
        $fanficWork = new Work(WorkType::Fanfiction, 'Shared Fanfic');
        $this->em->persist($fanficWork);
        $this->em->flush();

        // Alice: 1 entry for the fanfic; Bob: 4 entries for the same fanfic
        $this->em->persist(new ReadingEntry($alice, $fanficWork, $status));
        for ($i = 0; $i < 4; $i++) {
            $this->em->persist(new ReadingEntry($bob, $fanficWork, $status));
        }
        $this->em->flush();

        /** @var ReadingEntryRepository $repo */
        $repo = static::getContainer()->get(ReadingEntryRepository::class);

        $aliceCounts = $repo->countByWorkType($alice);

        // Alice has 1 Fanfiction entry, not 5
        $this->assertArrayHasKey('Fanfiction', $aliceCounts);
        $this->assertSame(1, $aliceCounts['Fanfiction']);
    }

    public function test_word_count_stats_counts_only_own_entries(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');
        $status = $this->createStatus('Completed', true);

        // Shared work with 10000 words
        $work = new Work(WorkType::Book, 'Shared Book');
        $work->setWords(10000);
        $this->em->persist($work);
        $this->em->flush();

        // Alice: 1 finished entry; Bob: 4 finished entries
        $aliceEntry = new ReadingEntry($alice, $work, $status);
        $aliceEntry->setDateFinished(new DateTimeImmutable('2025-01-01'));
        $this->em->persist($aliceEntry);

        for ($i = 0; $i < 4; $i++) {
            $entry = new ReadingEntry($bob, $work, $status);
            $entry->setDateFinished(new DateTimeImmutable('2025-01-01'));
            $this->em->persist($entry);
        }
        $this->em->flush();

        /** @var ReadingEntryRepository $repo */
        $repo = static::getContainer()->get(ReadingEntryRepository::class);

        $stats = $repo->getWordCountStats($alice);

        // Alice: 1 entry × 10000 words = 10000 total, not 50000 (5 entries × 10000)
        $this->assertSame(10000, $stats['totalWords']);
        $this->assertSame(1, $stats['entryCount']);
    }
}
