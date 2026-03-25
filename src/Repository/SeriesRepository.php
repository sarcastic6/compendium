<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Series::class);
    }

    /**
     * Search series by name (partial match), ordered by name, limited to 15 results.
     * Used by the autocomplete API endpoint.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function searchByName(string $term): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id', 's.name')
            ->where('LOWER(s.name) LIKE LOWER(:term)')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('LOWER(s.name)', 'ASC')
            ->setMaxResults(15)
            ->getQuery()
            ->getArrayResult();
    }
}
