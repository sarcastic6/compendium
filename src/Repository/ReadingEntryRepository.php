<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReadingEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingEntry>
 */
class ReadingEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingEntry::class);
    }

    /**
     * Fetches a paginated list of reading entries for a user, with JOIN FETCH
     * to avoid N+1 queries on Work and Status.
     *
     * The SoftDeleteFilter is temporarily disabled so that reading entries
     * that reference soft-deleted works still appear (with a visual indicator).
     *
     * @return ReadingEntry[]
     */
    public function findByUser(User $user, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;
        $em = $this->getEntityManager();
        $filters = $em->getFilters();

        // Temporarily disable the soft-delete filter so deleted works are still visible
        // on reading entries that reference them (per design: preserve history).
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $this->createQueryBuilder('re')
                ->innerJoin('re.work', 'w')
                ->addSelect('w')
                ->innerJoin('re.status', 's')
                ->addSelect('s')
                ->where('re.user = :user')
                ->setParameter('user', $user)
                ->orderBy('re.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Fetches a paginated, filtered list of reading entries for a user.
     *
     * Supported filters (all optional):
     *   - status (int): status ID exact match
     *   - q (string): case-insensitive LIKE on work title
     *   - author (string): case-insensitive LIKE on author metadata name
     *   - starred (bool): entry starred flag
     *   - rating (int): exact reviewStars match
     *   - dateFrom (string: Y-m-d): dateFinished >= this date
     *   - dateTo (string: Y-m-d): dateFinished <= this date
     *
     * The SoftDeleteFilter is temporarily disabled so entries referencing
     * soft-deleted works still appear.
     *
     * @param array<string, mixed> $filterParams
     * @return ReadingEntry[]
     */
    public function findByUserFiltered(User $user, array $filterParams, int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;
        $em = $this->getEntityManager();
        $emFilters = $em->getFilters();

        $softDeleteEnabled = $emFilters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $emFilters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('re')
                ->innerJoin('re.work', 'w')
                ->addSelect('w')
                ->innerJoin('re.status', 's')
                ->addSelect('s')
                ->where('re.user = :user')
                ->setParameter('user', $user)
                ->orderBy('re.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit);

            $this->applyFilters($qb, $filterParams);

            return $qb->getQuery()->getResult();
        } finally {
            if ($softDeleteEnabled) {
                $emFilters->enable('soft_delete');
            }
        }
    }

    /**
     * Counts filtered entries for a user. Used for pagination alongside findByUserFiltered().
     *
     * @param array<string, mixed> $filterParams
     */
    public function countByUserFiltered(User $user, array $filterParams): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(DISTINCT re.id)')
            ->innerJoin('re.work', 'w')
            ->where('re.user = :user')
            ->setParameter('user', $user);

        $this->applyFilters($qb, $filterParams);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Fetches a single reading entry by ID, scoped to the given user.
     * Returns null if the entry doesn't exist or belongs to a different user.
     *
     * The SoftDeleteFilter is disabled so entries referencing soft-deleted works
     * still load correctly (same reasoning as findByUser).
     */
    public function findByIdForUser(int $id, User $user): ?ReadingEntry
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();

        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $this->createQueryBuilder('re')
                ->innerJoin('re.work', 'w')
                ->addSelect('w')
                ->innerJoin('re.status', 's')
                ->addSelect('s')
                ->leftJoin('re.mainPairing', 'mp')
                ->addSelect('mp')
                ->where('re.id = :id')
                ->andWhere('re.user = :user')
                ->setParameter('id', $id)
                ->setParameter('user', $user)
                ->getQuery()
                ->getOneOrNullResult();
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Total reading entry count for the given user.
     * When $year is provided, only counts entries with dateFinished in that year.
     */
    public function countByUser(User $user, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->where('re.user = :user')
            ->setParameter('user', $user);

        $this->applyYearFilter($qb, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Sum of work word counts across reading entries for the given user where
     * the user has actually read the work (status.hasBeenStarted = true).
     * TBR entries are excluded; DNF entries are included (user read some of it).
     *
     * Works without a word count (NULL) are treated as zero.
     * When $year is provided, only sums entries with dateFinished in that year.
     *
     * The SoftDeleteFilter is disabled so soft-deleted works still contribute.
     */
    public function getTotalWordsSumForUser(User $user, ?int $year = null): int
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('re')
                ->select('SUM(COALESCE(w.words, 0))')
                ->innerJoin('re.work', 'w')
                ->innerJoin('re.status', 's')
                ->where('re.user = :user')
                ->andWhere('s.hasBeenStarted = :started')
                ->setParameter('user', $user)
                ->setParameter('started', true);

            $this->applyYearFilter($qb, $year);

            return (int) ($qb->getQuery()->getSingleScalarResult() ?? 0);
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Applies optional filter conditions to a QueryBuilder.
     * The 'w' alias for Work must already be joined before calling this.
     *
     * @param array<string, mixed> $filterParams
     */
    // -------------------------------------------------------------------------
    // Aggregate / statistics query methods
    // -------------------------------------------------------------------------

    /**
     * Returns distinct years in which the user recorded a dateFinished, sorted
     * descending (most recent first). Used to populate the year-filter dropdown.
     *
     * PHP grouping is used for DB portability (avoids YEAR() / EXTRACT()).
     *
     * @return int[]
     */
    public function findAvailableYears(User $user): array
    {
        $rows = $this->createQueryBuilder('re')
            ->select('re.dateFinished')
            ->where('re.user = :user')
            ->andWhere('re.dateFinished IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $years = [];
        foreach ($rows as $row) {
            $date = $row['dateFinished'];
            if ($date instanceof \DateTimeInterface) {
                $years[(int) $date->format('Y')] = true;
            }
        }

        $years = array_keys($years);
        rsort($years);

        return $years;
    }

    /**
     * Entry count grouped by status name for the given user.
     * When $year is provided, only counts entries with dateFinished in that year.
     *
     * @return array<string, int>
     */
    public function countByStatus(User $user, ?int $year = null): array
    {
        $qb = $this->createQueryBuilder('re')
            ->select('s.name as statusName, COUNT(re.id) as cnt')
            ->innerJoin('re.status', 's')
            ->where('re.user = :user')
            ->setParameter('user', $user)
            ->groupBy('s.id, s.name');

        $this->applyYearFilter($qb, $year);

        $rows = $qb->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['statusName']] = (int) $row['cnt'];
        }

        arsort($result);

        return $result;
    }

    /**
     * Entry count grouped by work type (Book/Fanfiction) for the given user.
     * When $year is provided, only counts entries with dateFinished in that year.
     *
     * The SoftDeleteFilter is disabled so entries referencing soft-deleted works
     * still contribute to the type count.
     *
     * @return array<string, int>
     */
    public function countByWorkType(User $user, ?int $year = null): array
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('re')
                ->select('w.type as workType, COUNT(re.id) as cnt')
                ->innerJoin('re.work', 'w')
                ->where('re.user = :user')
                ->setParameter('user', $user)
                ->groupBy('w.type');

            $this->applyYearFilter($qb, $year);

            $rows = $qb->getQuery()->getArrayResult();
            $result = [];
            foreach ($rows as $row) {
                $workType = $row['workType'];
                $typeName = $workType instanceof \App\Enum\WorkType
                    ? $workType->value
                    : (string) $workType;
                $result[$typeName] = (int) $row['cnt'];
            }

            arsort($result);

            return $result;
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Aggregate word count stats for entries where the user has actually read
     * the work (status.hasBeenStarted = true or status.countsAsRead = true —
     * i.e., any status other than TBR).
     * Only entries whose work has a non-NULL word count are counted.
     *
     * Returns:
     *   - totalWords:   sum of word counts across matched entries
     *   - averageWords: average word count (null if no entries match)
     *   - entryCount:   count of matched entries (denominator for averageWords)
     *
     * The SoftDeleteFilter is disabled so soft-deleted works still count.
     * The entryCount may be less than the total finished count when some works
     * have no word count; the template should display a contextual subtitle.
     *
     * @return array{totalWords: int, averageWords: float|null, entryCount: int}
     */
    public function getWordCountStats(User $user, ?int $year = null): array
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $base = $this->createQueryBuilder('re')
                ->innerJoin('re.work', 'w')
                ->innerJoin('re.status', 's')
                ->where('re.user = :user')
                ->andWhere('s.hasBeenStarted = :started')
                ->andWhere('w.words IS NOT NULL')
                ->setParameter('user', $user)
                ->setParameter('started', true);

            $this->applyYearFilter($base, $year);

            // Three separate scalar queries to avoid hydration-mode complexity.
            // COALESCE is defensive; the IS NOT NULL filter above already excludes NULLs.
            $totalWords = (int) ((clone $base)
                ->select('SUM(COALESCE(w.words, 0))')
                ->getQuery()
                ->getSingleScalarResult() ?? 0);

            $avgRaw = (clone $base)
                ->select('AVG(w.words)')
                ->getQuery()
                ->getSingleScalarResult();

            $entryCount = (int) (clone $base)
                ->select('COUNT(re.id)')
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'totalWords' => $totalWords,
                'averageWords' => $avgRaw !== null ? round((float) $avgRaw) : null,
                'entryCount' => $entryCount,
            ];
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Counts completed entries (status.countsAsRead = true) per month for the
     * given year. Returns array<int, int> keyed 1–12, zero-filled.
     *
     * PHP grouping is used instead of MONTH() for DB portability.
     *
     * @return array<int, int>
     */
    public function countByMonth(User $user, int $year): array
    {
        $yearStart = new \DateTimeImmutable("$year-01-01");
        $yearEnd = new \DateTimeImmutable("$year-12-31");

        $rows = $this->createQueryBuilder('re')
            ->select('re.dateFinished')
            ->innerJoin('re.status', 's')
            ->where('re.user = :user')
            ->andWhere('s.countsAsRead = :countsAsRead')
            ->andWhere('re.dateFinished >= :yearStart')
            ->andWhere('re.dateFinished <= :yearEnd')
            ->setParameter('user', $user)
            ->setParameter('countsAsRead', true)
            ->setParameter('yearStart', $yearStart, Types::DATE_IMMUTABLE)
            ->setParameter('yearEnd', $yearEnd, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(range(1, 12), 0);
        foreach ($rows as $row) {
            $date = $row['dateFinished'];
            if ($date instanceof \DateTimeInterface) {
                $counts[(int) $date->format('n')]++;
            }
        }

        return $counts;
    }

    /**
     * Counts completed entries (status.countsAsRead = true) per calendar year.
     * Returns array<int, int> keyed by year, sorted ascending.
     * Used for the all-time trend chart.
     *
     * PHP grouping is used instead of YEAR() for DB portability.
     *
     * @return array<int, int>
     */
    public function countByYear(User $user): array
    {
        $rows = $this->createQueryBuilder('re')
            ->select('re.dateFinished')
            ->innerJoin('re.status', 's')
            ->where('re.user = :user')
            ->andWhere('s.countsAsRead = :countsAsRead')
            ->andWhere('re.dateFinished IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('countsAsRead', true)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $date = $row['dateFinished'];
            if ($date instanceof \DateTimeInterface) {
                $year = (int) $date->format('Y');
                $counts[$year] = ($counts[$year] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Histogram of reviewStars (1–5) for the given user, zero-filled for all
     * values 1–5 so the chart always shows the full scale.
     *
     * @return array<int, int>
     */
    public function getRatingDistribution(User $user, ?int $year = null): array
    {
        $qb = $this->createQueryBuilder('re')
            ->select('re.reviewStars as stars, COUNT(re.id) as cnt')
            ->where('re.user = :user')
            ->andWhere('re.reviewStars IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('re.reviewStars')
            ->orderBy('re.reviewStars', 'ASC');

        $this->applyYearFilter($qb, $year);

        $rows = $qb->getQuery()->getArrayResult();
        $result = array_fill_keys(range(1, 5), 0);
        foreach ($rows as $row) {
            $result[(int) $row['stars']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * Histogram of spiceStars (0–5) for the given user, zero-filled for all
     * values 0–5 so the chart always shows the full scale.
     *
     * @return array<int, int>
     */
    public function getSpiceDistribution(User $user, ?int $year = null): array
    {
        $qb = $this->createQueryBuilder('re')
            ->select('re.spiceStars as stars, COUNT(re.id) as cnt')
            ->where('re.user = :user')
            ->andWhere('re.spiceStars IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('re.spiceStars')
            ->orderBy('re.spiceStars', 'ASC');

        $this->applyYearFilter($qb, $year);

        $rows = $qb->getQuery()->getArrayResult();
        $result = array_fill_keys(range(0, 5), 0);
        foreach ($rows as $row) {
            $result[(int) $row['stars']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * Top $limit metadata entries of the given type, ranked by how many of the
     * user's reading entries reference a work with that metadata.
     *
     * CRITICAL: The query is anchored on reading_entries WHERE user = :user.
     * Without this anchor, counts would include other users' entries referencing
     * the same (global) works, producing inflated results.
     *
     * The SoftDeleteFilter is disabled so entries referencing soft-deleted works
     * still contribute to metadata counts.
     *
     * @return array<array{name: string, count: int}>
     */
    public function getTopMetadata(
        User $user,
        string $typeName,
        int $limit,
        ?int $year = null,
    ): array {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('re')
                ->select('m.name as name, COUNT(re.id) as cnt')
                ->innerJoin('re.work', 'w')
                ->innerJoin('w.metadata', 'm')
                ->innerJoin('m.metadataType', 'mt')
                ->where('re.user = :user')
                ->andWhere('mt.name = :typeName')
                ->setParameter('user', $user)
                ->setParameter('typeName', $typeName)
                ->groupBy('m.id, m.name')
                ->orderBy('COUNT(re.id)', 'DESC')
                ->setMaxResults($limit);

            $this->applyYearFilter($qb, $year);

            $rows = $qb->getQuery()->getArrayResult();

            return array_map(
                static fn (array $row) => ['name' => (string) $row['name'], 'count' => (int) $row['cnt']],
                $rows,
            );
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Count of starred reading entries for the given user.
     */
    public function countStarred(User $user, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->where('re.user = :user')
            ->andWhere('re.starred = :starred')
            ->setParameter('user', $user)
            ->setParameter('starred', true);

        $this->applyYearFilter($qb, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Average reviewStars for entries that have a rating, for the given user.
     * Returns null when no rated entries exist.
     */
    public function getAverageRating(User $user, ?int $year = null): ?float
    {
        $qb = $this->createQueryBuilder('re')
            ->select('AVG(re.reviewStars)')
            ->where('re.user = :user')
            ->andWhere('re.reviewStars IS NOT NULL')
            ->setParameter('user', $user);

        $this->applyYearFilter($qb, $year);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? round((float) $result, 1) : null;
    }

    /**
     * Count of entries where status.countsAsRead = true for the given user.
     * When $year is provided, also filters on dateFinished within that year.
     */
    public function countFinished(User $user, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->innerJoin('re.status', 's')
            ->where('re.user = :user')
            ->andWhere('s.countsAsRead = :countsAsRead')
            ->setParameter('user', $user)
            ->setParameter('countsAsRead', true);

        $this->applyYearFilter($qb, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count of "started" entries: entries whose status has hasBeenStarted = true
     * (Reading, On Hold, Completed, DNF — anything except TBR).
     * Used as the denominator for the finish rate.
     *
     * When $year is provided, also filters on dateFinished within that year.
     */
    public function countStarted(User $user, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->innerJoin('re.status', 's')
            ->where('re.user = :user')
            ->andWhere('s.hasBeenStarted = :started')
            ->setParameter('user', $user)
            ->setParameter('started', true);

        $this->applyYearFilter($qb, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count of distinct works across all entries for the given user.
     * When $year is provided, counts only works from entries finished that year.
     */
    public function countUniqueWorks(User $user, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('re')
            ->select('COUNT(DISTINCT re.work)')
            ->where('re.user = :user')
            ->setParameter('user', $user);

        $this->applyYearFilter($qb, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Average spiceStars for entries that have a spice rating (including 0 = ice
     * cold), for the given user. Returns null when no rated entries exist.
     */
    public function getAverageSpice(User $user, ?int $year = null): ?float
    {
        $qb = $this->createQueryBuilder('re')
            ->select('AVG(re.spiceStars)')
            ->where('re.user = :user')
            ->andWhere('re.spiceStars IS NOT NULL')
            ->setParameter('user', $user);

        $this->applyYearFilter($qb, $year);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? round((float) $result, 1) : null;
    }

    /**
     * Returns full ranking data for all metadata entries of the given type.
     * No limit is applied — returns all results; caller is responsible for sorting.
     *
     * Each row contains:
     *   - name:       metadata entry name
     *   - count:      total reading entries referencing this metadata (re-reads included)
     *   - totalWords: sum of work word counts across all matched reading entries
     *                 (NULL word counts treated as zero; re-reads multiply the word count)
     *   - readCount:  count of reading entries where status.countsAsRead = true
     *
     * Two separate queries are used (one for count+words, one for readCount) to
     * avoid relying on CASE WHEN inside aggregate functions, which is not reliably
     * portable across Doctrine DQL versions.
     *
     * IMPORTANT: The query is anchored on reading_entries WHERE user = :user to
     * prevent cross-user inflation from shared global works.
     *
     * The SoftDeleteFilter is disabled so entries referencing soft-deleted works
     * still contribute to metadata counts.
     *
     * @return array<array{name: string, count: int, totalWords: int, readCount: int}>
     */
    public function getMetadataRankings(User $user, string $typeName, ?int $year = null): array
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            // Query 1: entry count only (all statuses — Count # reflects all reading activity)
            $qb = $this->createQueryBuilder('re')
                ->select('m.id as mid, m.name as name, COUNT(re.id) as cnt')
                ->innerJoin('re.work', 'w')
                ->innerJoin('w.metadata', 'm')
                ->innerJoin('m.metadataType', 'mt')
                ->where('re.user = :user')
                ->andWhere('mt.name = :typeName')
                ->setParameter('user', $user)
                ->setParameter('typeName', $typeName)
                ->groupBy('m.id, m.name');

            $this->applyYearFilter($qb, $year);

            $mainRows = $qb->getQuery()->getArrayResult();

            // Query 2: total words — only entries where the user actually read the work
            // (hasBeenStarted = true). TBR contributes zero words; DNF counts (user read some).
            $qb2 = $this->createQueryBuilder('re')
                ->select('m.id as mid, SUM(COALESCE(w.words, 0)) as totalWords')
                ->innerJoin('re.work', 'w')
                ->innerJoin('w.metadata', 'm')
                ->innerJoin('m.metadataType', 'mt')
                ->innerJoin('re.status', 's')
                ->where('re.user = :user')
                ->andWhere('mt.name = :typeName')
                ->andWhere('s.hasBeenStarted = :started')
                ->setParameter('user', $user)
                ->setParameter('typeName', $typeName)
                ->setParameter('started', true)
                ->groupBy('m.id');

            $this->applyYearFilter($qb2, $year);

            $wordsRows = $qb2->getQuery()->getArrayResult();

            // Query 3: read count (countsAsRead entries only — excludes DNF, TBR, Reading, On Hold)
            $qb3 = $this->createQueryBuilder('re')
                ->select('m.id as mid, COUNT(re.id) as readCnt')
                ->innerJoin('re.work', 'w')
                ->innerJoin('w.metadata', 'm')
                ->innerJoin('m.metadataType', 'mt')
                ->innerJoin('re.status', 's')
                ->where('re.user = :user')
                ->andWhere('mt.name = :typeName')
                ->andWhere('s.countsAsRead = :countsAsRead')
                ->setParameter('user', $user)
                ->setParameter('typeName', $typeName)
                ->setParameter('countsAsRead', true)
                ->groupBy('m.id');

            $this->applyYearFilter($qb3, $year);

            $readRows = $qb3->getQuery()->getArrayResult();

            // Index words and read counts by metadata ID for O(1) lookup
            $wordTotals = [];
            foreach ($wordsRows as $row) {
                $wordTotals[(int) $row['mid']] = (int) $row['totalWords'];
            }

            $readCounts = [];
            foreach ($readRows as $row) {
                $readCounts[(int) $row['mid']] = (int) $row['readCnt'];
            }

            return array_map(
                static fn (array $row): array => [
                    'name' => (string) $row['name'],
                    'count' => (int) $row['cnt'],
                    'totalWords' => $wordTotals[(int) $row['mid']] ?? 0,
                    'readCount' => $readCounts[(int) $row['mid']] ?? 0,
                ],
                $mainRows,
            );
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    /**
     * Returns the names of metadata types for which the user has at least one
     * reading entry (through the work → works_metadata → metadata chain).
     * Used to populate the rankings link section on the dashboard.
     *
     * The SoftDeleteFilter is disabled so soft-deleted works still contribute.
     *
     * @return string[]
     */
    public function findAvailableMetadataTypeNames(User $user, ?int $year = null): array
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('re')
                ->select('mt.name as typeName')
                ->innerJoin('re.work', 'w')
                ->innerJoin('w.metadata', 'm')
                ->innerJoin('m.metadataType', 'mt')
                ->where('re.user = :user')
                ->setParameter('user', $user)
                ->groupBy('mt.id, mt.name')
                ->orderBy('mt.name', 'ASC');

            $this->applyYearFilter($qb, $year);

            $rows = $qb->getQuery()->getArrayResult();

            return array_column($rows, 'typeName');
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Applies a year filter on dateFinished to a QueryBuilder.
     * When $year is null this is a no-op (all-time view).
     */
    private function applyYearFilter(QueryBuilder $qb, ?int $year): void
    {
        if ($year === null) {
            return;
        }

        // Types::DATE_IMMUTABLE ensures Doctrine serializes the boundary as 'Y-m-d'
        // (not 'Y-m-d H:i:s'), so SQLite string comparison correctly includes
        // entries on Jan 1 and Dec 31 of the selected year.
        $qb->andWhere('re.dateFinished >= :yearStart')
            ->andWhere('re.dateFinished <= :yearEnd')
            ->setParameter('yearStart', new \DateTimeImmutable("$year-01-01"), Types::DATE_IMMUTABLE)
            ->setParameter('yearEnd', new \DateTimeImmutable("$year-12-31"), Types::DATE_IMMUTABLE);
    }

    private function applyFilters(QueryBuilder $qb, array $filterParams): void
    {
        if (!empty($filterParams['status'])) {
            $qb->andWhere('re.status = :filter_status')
                ->setParameter('filter_status', (int) $filterParams['status']);
        }

        if (!empty($filterParams['q'])) {
            $qb->andWhere('w.title LIKE :filter_q')
                ->setParameter('filter_q', '%' . $filterParams['q'] . '%');
        }

        if (!empty($filterParams['author'])) {
            // Join through the works_metadata junction to filter by Author metadata name.
            // DISTINCT is used on the caller side to avoid duplicate rows when a work
            // has multiple metadata entries matching the author pattern.
            $qb->innerJoin('w.metadata', 'm_author')
                ->innerJoin('m_author.metadataType', 'mt_author')
                ->andWhere('mt_author.name = :author_type')
                ->andWhere('m_author.name LIKE :filter_author')
                ->setParameter('author_type', 'Author')
                ->setParameter('filter_author', '%' . $filterParams['author'] . '%');
        }

        if (isset($filterParams['starred']) && $filterParams['starred'] !== '') {
            $qb->andWhere('re.starred = :filter_starred')
                ->setParameter('filter_starred', (bool) $filterParams['starred']);
        }

        if (!empty($filterParams['rating'])) {
            $qb->andWhere('re.reviewStars = :filter_rating')
                ->setParameter('filter_rating', (int) $filterParams['rating']);
        }

        if (!empty($filterParams['dateFrom'])) {
            $qb->andWhere('re.dateFinished >= :filter_date_from')
                ->setParameter('filter_date_from', new \DateTimeImmutable($filterParams['dateFrom']));
        }

        if (!empty($filterParams['dateTo'])) {
            $qb->andWhere('re.dateFinished <= :filter_date_to')
                ->setParameter('filter_date_to', new \DateTimeImmutable($filterParams['dateTo']));
        }

        // Spice stars: 0 is a valid value so check !== '' rather than !empty
        if (isset($filterParams['spice']) && $filterParams['spice'] !== '') {
            $qb->andWhere('re.spiceStars = :filter_spice')
                ->setParameter('filter_spice', (int) $filterParams['spice']);
        }

        if (!empty($filterParams['type'])) {
            // Validate against the WorkType enum to silently ignore invalid values.
            // The 'w' alias for Work is always joined before applyFilters is called.
            $workType = \App\Enum\WorkType::tryFrom($filterParams['type']);
            if ($workType !== null) {
                $qb->andWhere('w.type = :filter_type')
                    ->setParameter('filter_type', $workType);
            }
        }
    }
}
