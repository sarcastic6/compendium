<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Work;
use App\Enum\ScrapeStatus;
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
     * Title search for the work autocomplete API.
     *
     * Returns up to $limit active (non-deleted) Work entities whose title
     * contains $q, ordered by title. Author metadata is joined eagerly to
     * avoid N+1 queries when the caller formats the subtitle string.
     *
     * Author names are assembled in PHP rather than via SQL aggregation
     * (GROUP_CONCAT / STRING_AGG) to remain database-portable across
     * SQLite, MySQL, and PostgreSQL.
     *
     * @return Work[]
     */
    public function searchByTitle(string $q, int $limit = 15): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.metadata', 'm')->addSelect('m')
            ->leftJoin('m.metadataType', 'mt')->addSelect('mt')
            ->where('w.title LIKE :q')
            ->orderBy('w.title', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('q', '%' . $q . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all works with the given scrape status, ordered by creation date descending.
     * Used by the scrape status page to surface pending and failed scrape jobs.
     *
     * @return Work[]
     */
    public function findByScrapeStatus(ScrapeStatus $status): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.scrapeStatus = :status')
            ->setParameter('status', $status->value)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
