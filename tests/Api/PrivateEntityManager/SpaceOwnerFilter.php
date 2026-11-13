<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * A host SQL filter (spaces of one owner, or of a list of owners) mirrored onto the private manager.
 */
final class SpaceOwnerFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getName() !== WikiSpace::class) {
            return '';
        }
        if ($this->hasParameter('owners')) {
            return $targetTableAlias . '.owner_scope_id IN (' . $this->getParameterList('owners') . ')';
        }

        return $targetTableAlias . '.owner_scope_id = ' . $this->getParameter('owner');
    }
}
