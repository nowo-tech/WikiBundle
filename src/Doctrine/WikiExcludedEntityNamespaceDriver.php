<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiMappingManagerPass;

use function sprintf;

/**
 * Metadata driver that maps nothing (1.32.0, the LegalKit 1.13.1 pattern): registered for the wiki entity namespace
 * on the entity managers other than `nowo_wiki.database.entity_manager` by {@see WikiMappingManagerPass}.
 *
 * A {@see MappingDriverChain} asks its namespace drivers before its default driver, so the wiki entities stay transient
 * on those managers even when their chain has a default driver that claims every class carrying `#[ORM\Entity]`
 * (DoctrineBundle 3.4 `auto_discover_entities`, on by default for a manager without `mappings` and `auto_mapping`).
 * Stateless (worker-safe).
 */
final class WikiExcludedEntityNamespaceDriver implements MappingDriver
{
    /**
     * Always fails: no wiki class is mapped on this manager.
     *
     * @throws MappingException
     */
    public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
    {
        throw new MappingException(sprintf('Class "%s" is not mapped on this entity manager (nowo_wiki.database.entity_manager maps it on another one).', $className));
    }

    /**
     * No class: the wiki entities are listed by the configured manager only.
     *
     * @return list<class-string>
     */
    public function getAllClassNames(): array
    {
        return [];
    }

    /**
     * Every wiki class is transient on this manager.
     */
    public function isTransient(string $className): bool
    {
        return true;
    }
}
