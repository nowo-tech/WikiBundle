<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function implode;
use function ksort;
use function sort;
use function strtolower;

/**
 * 1.49.1 (audit XI-2): the collation listener must not change the foreign keys or indexes of the generated schema.
 *
 * Up to 1.49.0 the listener rebuilt the table through the DBAL 4.5+ editor and handed it back with setClassTable();
 * ORM 3.7.0 to 3.8.0 then added the deferred foreign keys to the table it had replaced, so on MySQL / MariaDB every
 * foreign key starting on a collated table (and the index it needs) was missing: `wiki_page_links.page_id` (CASCADE)
 * and `created_by_id` (SET NULL), `wiki_page_favourites.page_id`, `wiki_page_visits.page_id` and
 * `wiki_page_references.source_page_id` (CASCADE), and in id mode the page foreign keys of every table with a user id
 * column. The servers are never connected to: the `serverVersion` decides the platform.
 */
final class WikiSchemaForeignKeysTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function platforms(): iterable
    {
        foreach (['MySQL 8.4' => 'mysql://u@127.0.0.1/wiki?serverVersion=8.4.0', 'MySQL 8.0' => 'mysql://u@127.0.0.1/wiki?serverVersion=8.0.36', 'MySQL 5.7' => 'mysql://u@127.0.0.1/wiki?serverVersion=5.7.44', 'MariaDB 11.4' => 'mysql://u@127.0.0.1/wiki?serverVersion=11.4.0-MariaDB'] as $name => $url) {
            yield $name . ', entity mode' => [$url, WikiMetadataListener::USER_REFERENCE_ENTITY];
            yield $name . ', id mode' => [$url, WikiMetadataListener::USER_REFERENCE_ID];
        }
    }

    #[DataProvider('platforms')]
    public function testTheListenerKeepsEveryForeignKeyAndIndex(string $url, string $userReference): void
    {
        $without = $this->describe($this->schema($url, $userReference, false));
        $with    = $this->describe($this->schema($url, $userReference, true));

        self::assertSame($without, $with);
    }

    #[DataProvider('platforms')]
    public function testTheAuditedForeignKeysAreGenerated(string $url, string $userReference): void
    {
        $schema = $this->schema($url, $userReference, true);
        $tables = $this->describe($schema);

        self::assertContains('page_id -> wiki_pages(id) ON DELETE CASCADE', $tables['wiki_page_links']['foreign keys']);
        self::assertContains('page_id -> wiki_pages(id) ON DELETE CASCADE', $tables['wiki_page_favourites']['foreign keys']);
        self::assertContains('page_id -> wiki_pages(id) ON DELETE CASCADE', $tables['wiki_page_visits']['foreign keys']);
        self::assertContains('source_page_id -> wiki_pages(id) ON DELETE CASCADE', $tables['wiki_page_references']['foreign keys']);
        if ($userReference === WikiMetadataListener::USER_REFERENCE_ENTITY) {
            self::assertContains('created_by_id -> test_users(id) ON DELETE SET NULL', $tables['wiki_page_links']['foreign keys']);
        }

        // The listener did collate those tables.
        self::assertNotNull($schema->getTable('wiki_page_links')->getColumn('object_id')->getPlatformOptions()['collation'] ?? null);
        self::assertNotNull($schema->getTable('wiki_page_favourites')->getColumn('user_id')->getPlatformOptions()['collation'] ?? null);
        self::assertNotNull($schema->getTable('wiki_page_references')->getColumn('target_page_id')->getPlatformOptions()['collation'] ?? null);
    }

    private function schema(string $url, string $userReference, bool $listener): Schema
    {
        $em = WikiTestDatabase::connect(null, $userReference, $url);
        if ($listener) {
            $em->getEventManager()->addEventListener(ToolEvents::postGenerateSchema, new WikiSpaceCollationListener($em));
        }

        return (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());
    }

    /**
     * Every table's foreign keys (columns, target, ON DELETE) and indexes (name, columns, unique).
     *
     * @return array<string, array{'foreign keys': list<string>, indexes: list<string>}>
     */
    private function describe(Schema $schema): array
    {
        $tables = [];
        foreach ($schema->getTables() as $table) {
            $foreignKeys = [];
            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignKeys[] = implode(', ', $foreignKey->getLocalColumns()) . ' -> ' . $foreignKey->getForeignTableName() . '(' . implode(', ', $foreignKey->getForeignColumns()) . ')' . ($foreignKey->onDelete() !== null ? ' ON DELETE ' . $foreignKey->onDelete() : '');
            }
            $indexes = [];
            foreach ($table->getIndexes() as $index) {
                $indexes[] = strtolower($index->getName()) . ($index->isUnique() ? ' UNIQUE' : '') . ' (' . implode(', ', $index->getColumns()) . ')';
            }
            sort($foreignKeys);
            sort($indexes);
            $tables[$table->getName()] = ['foreign keys' => $foreignKeys, 'indexes' => $indexes];
        }
        ksort($tables);

        return $tables;
    }
}
