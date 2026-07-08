<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;

use function array_replace_recursive;
use function ltrim;

/**
 * Applies configurable table names and user entity mapping to wiki entities.
 */
final readonly class WikiMetadataListener
{
    public function __construct(
        private string $spacesTableName,
        private string $pagesTableName,
        private string $revisionsTableName,
        private string $userClass,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $class    = $metadata->getName();

        if ($class === WikiSpace::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->spacesTableName]));

            return;
        }

        if ($class === WikiPage::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->pagesTableName]));

            return;
        }

        if ($class === WikiPageRevision::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->revisionsTableName]));
            $this->remapAuthorAssociation($metadata);

            return;
        }
    }

    private function remapAuthorAssociation(ClassMetadata $metadata): void
    {
        if (!isset($metadata->associationMappings['author'])) {
            return;
        }

        $mapping      = $metadata->associationMappings['author'];
        $targetEntity = ltrim($this->userClass, '\\');

        if ($mapping instanceof AssociationMapping) {
            $newMapping              = array_replace_recursive($mapping->toArray(), ['targetEntity' => $targetEntity]);
            $newMapping['fieldName'] = $mapping->fieldName;
            unset($metadata->associationMappings['author']);
            $metadata->mapManyToOne($newMapping);

            return;
        }

        /** @var array<string, mixed> $legacyMapping */
        $legacyMapping                           = $mapping;
        $legacyMapping['targetEntity']           = $targetEntity;
        $metadata->associationMappings['author'] = $legacyMapping;
    }
}
