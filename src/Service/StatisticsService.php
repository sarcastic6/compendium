<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\ReadingEntryRepository;

/**
 * Thin orchestrator for reading statistics. Delegates all SQL to
 * ReadingEntryRepository and handles only derived calculations (finish rate).
 */
class StatisticsService
{
    public function __construct(
        private readonly ReadingEntryRepository $readingEntryRepository,
    ) {
    }

    /**
     * Assembles all summary statistics for the dashboard.
     *
     * When $year is null (all-time view):
     *   - entryCount is the total number of entries (including TBR, Reading, etc.)
     * When $year is provided (year-filtered view):
     *   - entryCount is the count of entries finished in that year
     *
     * Word count stats always count only finished entries whose work has a
     * known word count; entryCount in wordCountStats may differ from finishedCount.
     *
     * @return array{
     *   entryCount: int,
     *   uniqueWorkCount: int,
     *   finishedCount: int,
     *   wordCountStats: array{totalWords: int, averageWords: float|null, entryCount: int},
     *   finishRate: float,
     *   averageRating: float|null,
     *   averageSpice: float|null,
     *   starredCount: int,
     *   byStatus: array<string, int>,
     *   availableYears: int[],
     * }
     */
    public function getDashboardSummary(User $user, ?int $year): array
    {
        $finished = $this->readingEntryRepository->countFinished($user, $year);
        $started = $this->readingEntryRepository->countStarted($user, $year);

        return [
            'entryCount' => $year !== null
                ? $finished
                : $this->readingEntryRepository->countByUser($user),
            'uniqueWorkCount' => $this->readingEntryRepository->countUniqueWorks($user, $year),
            'finishedCount' => $finished,
            'wordCountStats' => $this->readingEntryRepository->getWordCountStats($user, $year),
            'finishRate' => $this->calculateFinishRate($finished, $started),
            'averageRating' => $this->readingEntryRepository->getAverageRating($user, $year),
            'averageSpice' => $this->readingEntryRepository->getAverageSpice($user, $year),
            'starredCount' => $this->readingEntryRepository->countStarred($user, $year),
            'byStatus' => $this->readingEntryRepository->countByStatus($user, $year),
            'availableYears' => $this->readingEntryRepository->findAvailableYears($user),
        ];
    }

    /**
     * Returns trend data for the chart section.
     *
     * When $year is provided, returns monthly counts (array<int, int> keyed 1–12,
     * zero-filled) via countByMonth.
     *
     * When $year is null (all-time), returns yearly counts (array<int, int> keyed
     * by year) via countByYear.
     *
     * @return array<int, int>
     */
    public function getTrendData(User $user, ?int $year): array
    {
        if ($year !== null) {
            return $this->readingEntryRepository->countByMonth($user, $year);
        }

        return $this->readingEntryRepository->countByYear($user);
    }

    /**
     * Returns review and spice star distributions for the current user.
     *
     * @return array{review: array<int, int>, spice: array<int, int>}
     */
    public function getRatingDistributions(User $user, ?int $year): array
    {
        return [
            'review' => $this->readingEntryRepository->getRatingDistribution($user, $year),
            'spice' => $this->readingEntryRepository->getSpiceDistribution($user, $year),
        ];
    }

    /**
     * Pass-through to repository for top-N metadata by type.
     *
     * @return array<array{name: string, count: int}>
     */
    public function getTopMetadata(User $user, string $typeName, int $limit, ?int $year): array
    {
        return $this->readingEntryRepository->getTopMetadata($user, $typeName, $limit, $year);
    }

    /**
     * Returns full ranking data for a metadata type with all derived columns computed.
     *
     * Each item contains:
     *   - name:       metadata entry name
     *   - count:      total reading entries (re-reads included, year-scoped)
     *   - countPct:   count as % of all user entries (year-scoped)
     *   - totalWords: sum of word counts from matched reading entries (year-scoped)
     *   - wordsPct:   totalWords as % of all user words (year-scoped)
     *   - readCount:  finished reading entries (year-scoped)
     *   - readPct:    readCount / count * 100 (finish rate within this item)
     *
     * Valid $sortColumn values: name, count, count_pct, words, words_pct, read_count, read_pct
     * Valid $sortDir values: asc, desc
     * Default sort: count DESC
     *
     * IMPORTANT: count_pct and words_pct sort identically to count and words
     * respectively (percentage is proportional). read_pct is a distinct sort order
     * (finish rate may differ from raw entry count).
     *
     * @return array<array{name: string, count: int, countPct: float, totalWords: int, wordsPct: float, readCount: int, readPct: float}>
     */
    public function getRankings(
        User $user,
        string $typeName,
        string $sortColumn,
        string $sortDir,
        ?int $year,
    ): array {
        $rows = $this->readingEntryRepository->getMetadataRankings($user, $typeName, $year);

        // Count % denominator: sum of all counts for this metadata type.
        // This gives each item's share of appearances within this type, not a
        // share of global reading entries. Handles multi-value types (Character,
        // Tag, etc.) correctly — one entry contributes to multiple items, so the
        // global entry count is the wrong denominator.
        $totalEntries = array_sum(array_column($rows, 'count'));

        // Words % denominator: total words read globally by this user (year-scoped).
        // This gives each item's share of actual words read — "what percentage of
        // the words you read were in works featuring this item?" Using the sum of
        // per-item word totals would inflate the denominator for multi-value types
        // (a 100k word fic with 3 characters would count 300k words total).
        $totalWords = $this->readingEntryRepository->getTotalWordsSumForUser($user, $year);

        $items = array_map(
            static function (array $row) use ($totalEntries, $totalWords): array {
                return [
                    'name' => $row['name'],
                    'count' => $row['count'],
                    'countPct' => $totalEntries > 0
                        ? round($row['count'] / $totalEntries * 100, 1)
                        : 0.0,
                    'totalWords' => $row['totalWords'],
                    'wordsPct' => $totalWords > 0
                        ? round($row['totalWords'] / $totalWords * 100, 1)
                        : 0.0,
                    'readCount' => $row['readCount'],
                    'readPct' => $row['count'] > 0
                        ? round($row['readCount'] / $row['count'] * 100, 1)
                        : 0.0,
                ];
            },
            $rows,
        );

        usort($items, static function (array $a, array $b) use ($sortColumn, $sortDir): int {
            $valA = match ($sortColumn) {
                'name' => $a['name'],
                'count', 'count_pct' => $a['count'],
                'words', 'words_pct' => $a['totalWords'],
                'read_count' => $a['readCount'],
                'read_pct' => $a['readPct'],
                default => $a['count'],
            };
            $valB = match ($sortColumn) {
                'name' => $b['name'],
                'count', 'count_pct' => $b['count'],
                'words', 'words_pct' => $b['totalWords'],
                'read_count' => $b['readCount'],
                'read_pct' => $b['readPct'],
                default => $b['count'],
            };

            $cmp = is_string($valA)
                ? strcmp($valA, $valB)
                : ($valA <=> $valB);

            return $sortDir === 'asc' ? $cmp : -$cmp;
        });

        return $items;
    }

    /**
     * Finish rate: "of the works you started, what % did you finish?"
     * Returns 0.0 when no started entries exist (avoids division by zero).
     */
    public function getFinishRate(User $user, ?int $year): float
    {
        $finished = $this->readingEntryRepository->countFinished($user, $year);
        $started = $this->readingEntryRepository->countStarted($user, $year);

        return $this->calculateFinishRate($finished, $started);
    }

    /**
     * Returns the names of metadata types for which this user has at least one
     * reading entry (with optional year filter). Used for the rankings link
     * section on the dashboard — no link is shown for empty types.
     *
     * @return string[]
     */
    public function getAvailableRankingTypes(User $user, ?int $year): array
    {
        return $this->readingEntryRepository->findAvailableMetadataTypeNames($user, $year);
    }

    /**
     * Returns the most-read metadata entry for a given type name, plus any
     * other entries that tie for first place.
     *
     * IMPORTANT — counts by reading entries, not distinct works: re-reads of the
     * same work count separately. This reflects where the user's reading time
     * actually went rather than breadth of exposure.
     *
     * Returns null when the metadata type does not exist or has no entries.
     *
     * The "ties" key contains entries tied for first place beyond the first one
     * returned, so ties|length is the "+N" overflow count shown in the UI.
     *
     * @return array{name: string, count: int, ties: array<array{name: string, count: int}>}|null
     */
    public function getTopMetadataSpotlight(User $user, string $typeName, ?int $year): ?array
    {
        // Fetch enough rows to detect ties; >50-way ties at #1 are implausible.
        $rows = $this->readingEntryRepository->getTopMetadata($user, $typeName, 50, $year);

        if ($rows === []) {
            return null;
        }

        $topCount = $rows[0]['count'];
        $ties = array_values(array_filter(
            array_slice($rows, 1),
            static fn (array $row): bool => $row['count'] === $topCount,
        ));

        return [
            'name' => $rows[0]['name'],
            'count' => $topCount,
            'ties' => $ties,
        ];
    }

    private function calculateFinishRate(int $finished, int $started): float
    {
        if ($started === 0) {
            return 0.0;
        }

        return round($finished / $started * 100, 1);
    }
}
