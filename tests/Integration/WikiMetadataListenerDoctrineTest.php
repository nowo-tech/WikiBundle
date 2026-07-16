<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\ORMSetup;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use PHPUnit\Framework\TestCase;

use function dirname;

use const PHP_VERSION_ID;

final class WikiMetadataListenerDoctrineTest extends TestCase
{
    public function testRemapsAuthorAssociationForOrmAssociationMapping(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity'], true);
        // Doctrine ORM 3 needs LazyGhost (symfony/var-exporter) or PHP 8.4 native lazy objects.
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em   = new EntityManager($conn, $config);

        $metadata = $em->getClassMetadata(WikiPageRevision::class);
        $args     = new LoadClassMetadataEventArgs($metadata, $em);

        (new WikiMetadataListener('custom_spaces', 'custom_pages', 'custom_revisions', 'App\\Entity\\User'))
            ->loadClassMetadata($args);

        self::assertSame('custom_revisions', $metadata->table['name']);
        self::assertSame('App\\Entity\\User', $metadata->associationMappings['author']->targetEntity);
    }
}
