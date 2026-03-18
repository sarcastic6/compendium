<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Work>
 */
class WorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }

    /**
     * Finds an active (non-deleted) work by its source URL.
     * Used for duplicate detection during import.
     */
    public function findByLink(string $link): ?Work
    {
        return $this->findOneBy(['link' => $link]);
    }

    /**
     * Fetches a work with all related entities in a single query to avoid N+1 problems.
     * Used by the work detail page.
     */
    public function findWithAllRelations(int $id): ?Work
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.metadata', 'm')->addSelect('m')
            ->leftJoin('m.metadataType', 'mt')->addSelect('mt')
            ->leftJoin('m.sourceLinks', 'msl')->addSelect('msl')
            ->leftJoin('w.series', 's')->addSelect('s')
            ->leftJoin('w.language', 'l')->addSelect('l')
            ->where('w.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
