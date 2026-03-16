<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReadingEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
