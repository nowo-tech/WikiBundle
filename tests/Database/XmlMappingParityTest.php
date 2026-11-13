<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Nowo\WikiBundle\DependencyInjection\WikiDoctrineMapping;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Tests\Support\WikiXmlMappingGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function basename;
use function dirname;
use function file_get_contents;
use function get_object_vars;
use function getmypid;
use function glob;
use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function ksort;
use function sort;
use function str_starts_with;
use function sys_get_temp_dir;

use const CASE_LOWER;
use const PHP_VERSION_ID;

/**
 * 1.34.0 (REQ-ARCH-001 rule 2): the XML mapping of `Resources/config/doctrine-xml` (`database.mapping_type: xml`) gives
 * exactly the metadata of the ORM attributes, entity by entity (table, indexes, unique constraints, fields with their
 * columns, types, lengths, options and enum types, associations with their join columns, lifecycle callbacks), under
 * the default and the underscore naming strategies, with the bundle's metadata listener applied (table names, the user
 * class of the user relations); and the schema both generate on the test database's platform (SQLite, or MySQL /
 * PostgreSQL through `WIKI_TEST_DATABASE_URL`, with the owner id collation listener) is the same. The committed XML
 * files are the generator's output (`php scripts/generate-xml-mapping.php`).
 */
final class XmlMappingParityTest extends TestCase
{
    /** The wiki entities. */
    private const ENTITIES = 13;

    /**
     * @return iterable<string, array{0: NamingStrategy}>
     */
    public static function namingStrategies(): iterable
    {
        yield 'default naming' => [new DefaultNamingStrategy()];
        yield 'underscore naming' => [new UnderscoreNamingStrategy(CASE_LOWER, true)];
    }

    #[DataProvider('namingStrategies')]
    public function testEveryEntityMapsTheSameFromXml(NamingStrategy $naming): void
    {
        $attributes = $this->manager($naming, false);
        $xml        = $this->manager($naming, true);
        $classes    = [];
        foreach ($attributes->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (str_starts_with($metadata->getName(), 'Nowo\\WikiBundle\\Tests\\')) {
                continue; // the test host's user entity (the target of the user relations)
            }
            $classes[] = $metadata->getName();
            self::assertSame($this->normalize($metadata), $this->normalize($xml->getClassMetadata($metadata->getName())), $metadata->getName());
        }
        self::assertCount(self::ENTITIES, $classes, 'every wiki entity has an XML mapping');
        self::assertCount(self::ENTITIES + 1, $xml->getMetadataFactory()->getAllMetadata(), 'and nothing else (plus the test user)');
        self::assertSame(
            TestUserEntity::class,
            $xml->getClassMetadata('Nowo\\WikiBundle\\Entity\\WikiPageRevision')->getAssociationMapping('author')->targetEntity,
            'the user relations are remapped to user_class from XML too',
        );

        self::assertSame($this->schema($attributes), $this->schema($xml), 'the same tables, columns, indexes and foreign keys');
    }

    public function testTheCommittedFilesAreTheGeneratorOutput(): void
    {
        $src       = dirname(__DIR__, 2) . '/src';
        $generated = WikiXmlMappingGenerator::generate($src . '/Entity');
        $committed = [];
        foreach (glob(WikiDoctrineMapping::xmlDirectory() . '/*.orm.xml') ?: [] as $file) {
            $committed[basename($file)] = (string) file_get_contents($file);
        }
        ksort($committed);

        self::assertCount(self::ENTITIES, $generated);
        self::assertSame($generated, $committed, 'run php scripts/generate-xml-mapping.php after changing an entity');
    }

    /**
     * The generated schema, table by table, independent of the column order: each column's SQL declaration, the indexes
     * and the foreign keys.
     *
     * @return array<string, mixed>
     */
    private function schema(EntityManager $em): array
    {
        $platform = $em->getConnection()->getDatabasePlatform();
        $tables   = [];
        foreach ((new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata())->getTables() as $table) {
            $columns = [];
            foreach ($table->getColumns() as $column) {
                $columns[$column->getName()] = $platform->getColumnDeclarationSQL($column->getName(), $column->toArray());
            }
            ksort($columns);
            $indexes = [];
            foreach ($table->getIndexes() as $index) {
                $indexes[$index->getName()] = [$index->getColumns(), $index->isUnique(), $index->isPrimary()];
            }
            ksort($indexes);
            $keys = [];
            foreach ($table->getForeignKeys() as $key) {
                $keys[] = [$key->getLocalColumns(), $key->getForeignTableName(), $key->getForeignColumns(), $key->onDelete()];
            }
            sort($keys);
            $tables[$table->getName()] = ['columns' => $columns, 'indexes' => $indexes, 'foreignKeys' => $keys];
        }
        ksort($tables);

        return $tables;
    }

    private function manager(NamingStrategy $naming, bool $xml): EntityManager
    {
        $config = ORMSetup::createConfiguration(true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/wiki_xml_parity_proxies_' . getmypid());
            $config->setProxyNamespace('NowoWikiXmlParityProxies');
        }
        $config->setNamingStrategy($naming);
        $src   = dirname(__DIR__, 2) . '/src';
        $chain = new MappingDriverChain();
        $chain->addDriver(
            $xml ? new SimplifiedXmlDriver([WikiDoctrineMapping::xmlDirectory() => WikiDoctrineMapping::ENTITY_NAMESPACE]) : new AttributeDriver([$src . '/Entity']),
            WikiDoctrineMapping::ENTITY_NAMESPACE,
        );
        $chain->addDriver(new AttributeDriver([dirname(__DIR__) . '/Stub']), 'Nowo\\WikiBundle\\Tests\\Stub');
        $config->setMetadataDriverImpl($chain);
        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new WikiMetadataListener('wiki_spaces', 'wiki_pages', 'wiki_page_revisions', TestUserEntity::class));
        $url    = WikiTestDatabase::url();
        $params = $url === null ? ['driver' => 'pdo_sqlite', 'memory' => true] : (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $em     = new EntityManager(DriverManager::getConnection($params, $config), $config, $events);
        $events->addEventListener(ToolEvents::postGenerateSchemaTable, new WikiSpaceCollationListener($em));

        return $em;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, mixed>
     */
    private function normalize(ClassMetadata $metadata): array
    {
        $normalized = $this->plain([
            'table'              => $metadata->table,
            'identifier'         => $metadata->identifier,
            'fields'             => $metadata->fieldMappings,
            'associations'       => $metadata->associationMappings,
            'lifecycleCallbacks' => $metadata->lifecycleCallbacks,
            'repository'         => $metadata->customRepositoryClassName,
            'generator'          => $metadata->generatorType,
            'changeTracking'     => $metadata->changeTrackingPolicy,
            'readOnly'           => $metadata->isReadOnly,
        ]);
        self::assertIsArray($normalized);

        return $normalized;
    }

    /**
     * Objects as arrays, keys sorted. The drivers spell an unset flag differently (the attributes write `false`, the XML
     * driver leaves `null`; an empty options list against none): both mean the same and are compared as `null`.
     */
    private function plain(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if ($value === false || $value === []) {
            return null;
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach (array_keys($value) as $key) {
            if ($key === 'options' && is_array($value[$key])) {
                // A column option as XML keeps it (text): `0` / `false` in an attribute is "0" in XML.
                foreach ($value[$key] as $name => $option) {
                    $value[$key][$name] = is_bool($option) ? ($option ? '1' : '0') : (is_int($option) ? (string) $option : $option);
                }
            }
            // The attribute driver numbers a single association id (0); the XML driver does not.
            $value[$key] = $key === 'idPosition' ? null : $this->plain($value[$key]);
        }
        ksort($value);

        return $value;
    }
}
