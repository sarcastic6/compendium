<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use App\Entity\Work;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL filter that automatically excludes soft-deleted Work records.
 *
 * Applied only to the Work entity. All other entities are unaffected.
 * To temporarily include deleted works (e.g., for displaying deleted works on
 * reading entries), disable this filter on the EntityManager:
 *   $em->getFilters()->disable('soft_delete');
 */
class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getName() !== Work::class) {
            return '';
        }

        return $targetTableAlias . '.deleted_at IS NULL';
    }
}
