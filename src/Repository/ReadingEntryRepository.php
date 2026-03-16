<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReadingEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->where('re.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Applies optional filter conditions to a QueryBuilder.
     * The 'w' alias for Work must already be joined before calling this.
     *
     * @param array<string, mixed> $filterParams
     */
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
    }
}
