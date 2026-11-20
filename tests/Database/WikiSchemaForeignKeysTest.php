<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strtolower;

/**
 * 1.49.1 (audit XI-2): the tables the collation listener changes keep their foreign keys on a real database, and the
 * ON DELETE rules hold: deleting a page deletes its links, favourites, visits and outgoing references; deleting a user
 * clears `wiki_page_links.created_by_id`. Up to 1.49.0, MySQL / MariaDB with ORM 3.7.0 to 3.8.0 and DBAL 4.5+ got none
 * of those foreign keys. Runs on SQLite (with `foreign_keys` on), MySQL, MariaDB and PostgreSQL.
 */
final class WikiSchemaForeignKeysTest extends TestCase
{
    /** [table, local column, target table, ON DELETE] */
    private const FOREIGN_KEYS = [
        ['wiki_page_links', 'page_id', 'wiki_pages', 'CASCADE'],
        ['wiki_page_links', 'created_by_id', 'test_users', 'SET NULL'],
        ['wiki_page_favourites', 'page_id', 'wiki_pages', 'CASCADE'],
        ['wiki_page_visits', 'page_id', 'wiki_pages', 'CASCADE'],
        ['wiki_page_references', 'source_page_id', 'wiki_pages', 'CASCADE'],
    ];

    public function testTheCreatedSchemaHasTheForeignKeys(): void
    {
        $em            = WikiTestDatabase::create();
        $schemaManager = $em->getConnection()->createSchemaManager();
        foreach (self::FOREIGN_KEYS as [$table, $column, $target, $onDelete]) {
            $found = null;
            foreach ($schemaManager->listTableForeignKeys($table) as $foreignKey) {
                if (array_map(strtolower(...), $foreignKey->getLocalColumns()) === [$column]) {
                    $found = $foreignKey;
                }
            }
            self::assertInstanceOf(ForeignKeyConstraint::class, $found, $table . '.' . $column . ' has no foreign key');
            self::assertSame($target, strtolower($found->getForeignTableName()), $table . '.' . $column);
            self::assertSame($onDelete, $found->onDelete(), $table . '.' . $column);
        }
        $em->getConnection()->close();
    }

    public function testTheOnDeleteRulesHold(): void
    {
        $em = WikiTestDatabase::create();
        if (!WikiTestDatabase::isServer()) {
            // SQLite enforces foreign keys (and their ON DELETE actions) only when asked to.
            $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        }
        $ana    = new TestUserEntity('ana');
        $space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $page   = new WikiPage($space, 'runbook', 'Runbook');
        $target = new WikiPage($space, 'guide', 'Guide');
        $link   = new WikiPageLink($target, 'task', 'T-1', $ana);
        foreach ([$ana, $space, $page, $target, $link, new WikiPageLink($page, 'task', 'T-2', $ana), new WikiPageFavourite($page, 'ana'), new WikiPageVisit($page, 'ana', new DateTimeImmutable()), new WikiPageReference($page, $target->getId())] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
        $connection = $em->getConnection();

        // Deleting the user clears the link's author, the link stays.
        $connection->executeStatement('DELETE FROM test_users WHERE id = ?', ['ana']);
        self::assertSame(2, $this->rows($connection, 'wiki_page_links'));
        self::assertSame(0, $this->rows($connection, 'wiki_page_links', 'created_by_id IS NOT NULL'));

        // Deleting the page deletes its link, favourite, visit and outgoing reference; the other page's link stays.
        $connection->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);
        self::assertSame(1, $this->rows($connection, 'wiki_page_links'));
        self::assertSame(0, $this->rows($connection, 'wiki_page_favourites'));
        self::assertSame(0, $this->rows($connection, 'wiki_page_visits'));
        self::assertSame(0, $this->rows($connection, 'wiki_page_references'));
        $connection->close();
    }

    private function rows(Connection $connection, string $table, string $where = '1 = 1'): int
    {
        return (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
    }
}
