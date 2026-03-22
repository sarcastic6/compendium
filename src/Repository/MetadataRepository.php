<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Metadata;
use App\Entity\MetadataType;
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

    /**
     * Search metadata by name (partial match) scoped to a MetadataType.
     * Ordered by name, limited to 15 results.
     * Used by the autocomplete API endpoint.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function searchByNameAndType(string $term, MetadataType $type): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.name')
            ->where('LOWER(m.name) LIKE LOWER(:term)')
            ->andWhere('m.metadataType = :type')
            ->setParameter('term', '%' . $term . '%')
            ->setParameter('type', $type)
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(15)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns existing metadata values for types flagged showAsCheckboxes,
     * grouped by MetadataType ID, ordered by name.
     * Used to populate checkbox groups on the work form.
     *
     * @param MetadataType[] $types
     * @return array<int, array<array{id: int, name: string}>>
     */
    public function findCheckboxOptionsByTypes(array $types): array
    {
        if ($types === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('m.id', 'm.name', 'IDENTITY(m.metadataType) AS typeId')
            ->where('m.metadataType IN (:types)')
            ->setParameter('types', $types)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['typeId']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        return $grouped;
    }
}
