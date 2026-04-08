<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Series;
use App\Enum\SourceType;
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
     * Finds a Series by its source URL and source type.
     *
     * Used by findOrCreateSeries() for URL-first deduplication during import.
     * AO3 series names are not globally unique — two different authors can have
     * series with the same name — so URL matching is the reliable key.
     */
    public function findBySourceUrl(SourceType $sourceType, string $url): ?Series
    {
        return $this->createQueryBuilder('s')
            ->join('s.sourceLinks', 'ssl')
            ->where('ssl.sourceType = :sourceType')
            ->andWhere('ssl.link = :url')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('url', $url)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
