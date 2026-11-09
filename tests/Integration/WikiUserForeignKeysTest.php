<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function implode;
use function ksort;
use function str_replace;

/**
 * 1.26: what a host user's deletion does to the wiki. Every relation to the user class is nullable with
 * `ON DELETE SET NULL` (content stays), except a comment reaction, which is the user's own mark and goes with them.
 */
final class WikiUserForeignKeysTest extends TestCase
{
    public function testOnlyReactionsAreDeletedWithTheirUser(): void
    {
        $em    = SqliteWikiEntityManager::create();
        $found = [];
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->associationMappings as $field => $mapping) {
                if ($mapping->targetEntity !== TestUserEntity::class) {
                    continue;
                }
                self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);
                $column                                      = $mapping->joinColumns[0];
                $found[$metadata->getName() . '::' . $field] = [$column->name, $column->nullable ?? true, $column->onDelete];
            }
        }
        ksort($found);

        $kept = array_filter($found, static fn (array $column): bool => $column[1] && $column[2] === 'SET NULL');
        self::assertCount(7, $found);
        self::assertCount(6, $kept, implode(', ', array_keys($found)));
        self::assertSame(['user_id', false, 'CASCADE'], $found[WikiCommentReaction::class . '::user']);
        self::assertSame(['author_id', true, 'SET NULL'], $found[WikiPageRevision::class . '::author']);
    }

    public function testTheRevisionAuthorColumnIsNullableWithSetNull(): void
    {
        $em  = SqliteWikiEntityManager::create();
        $sql = implode("\n", (new SchemaTool($em))->getCreateSchemaSql([$em->getClassMetadata(WikiPageRevision::class)]));

        self::assertStringContainsString('author_id VARCHAR(36) DEFAULT NULL', $sql);
        self::assertMatchesRegularExpression('/FOREIGN KEY \(author_id\) REFERENCES test_users \(id\) ON DELETE SET NULL/', $sql);
        self::assertStringNotContainsString('ON DELETE CASCADE', str_replace('FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE', '', $sql));
    }
}
