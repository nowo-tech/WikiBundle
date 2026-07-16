<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Loads real ORM attribute metadata (AssociationMapping objects) without constructing
 * an EntityManager — avoids Doctrine proxy / LazyGhost requirements in CI matrices.
 */
final class WikiMetadataListenerDoctrineTest extends TestCase
{
    public function testRemapsAuthorAssociationForOrmAssociationMapping(): void
    {
        $driver   = new AttributeDriver([dirname(__DIR__, 2) . '/src/Entity']);
        $metadata = new ClassMetadata(WikiPageRevision::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $driver->loadMetadataForClass(WikiPageRevision::class, $metadata);

        $em   = $this->createMock(EntityManagerInterface::class);
        $args = new LoadClassMetadataEventArgs($metadata, $em);

        (new WikiMetadataListener('custom_spaces', 'custom_pages', 'custom_revisions', 'App\\Entity\\User'))
            ->loadClassMetadata($args);

        self::assertSame('custom_revisions', $metadata->table['name']);
        self::assertSame('App\\Entity\\User', $metadata->associationMappings['author']->targetEntity);
    }
}
