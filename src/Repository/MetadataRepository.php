<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Metadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Metadata>
 */
class MetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Metadata::class);
    }

    /**
     * Returns metadata names grouped by type name, for types flagged as show_as_dropdown.
     * Used to populate dropdown filter options on the reading entry list.
     * Only types with show_as_dropdown = true are included, preventing massive option lists
     * for high-cardinality types like Fandom, Character, or Tag.
     *
     * @return array<string, string[]> e.g. ['Rating' => ['General Audiences', 'Teen And Up', ...], ...]
     */
    public function findDropdownValuesByTypeName(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.name AS metaName, mt.name AS typeName')
            ->innerJoin('m.metadataType', 'mt')
            ->andWhere('mt.showAsDropdown = true')
            ->orderBy('mt.name')
            ->addOrderBy('m.name')
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['typeName']][] = $row['metaName'];
        }

        return $grouped;
    }
}
