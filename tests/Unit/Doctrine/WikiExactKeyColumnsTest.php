<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\WikiBundle\Doctrine\WikiExactKeyColumns;
use PHPUnit\Framework\TestCase;

use function array_map;
use function in_array;
use function str_starts_with;

/**
 * REQ-SEC-006: the declaration names the owner, host-object, person and backlink key columns of the wiki tables, on
 * the *configured* table names, and declares the columns of a feature that is off inactive instead of omitting them.
 */
final class WikiExactKeyColumnsTest extends TestCase
{
    /** Everything on, with the default table names. */
    private function all(): WikiExactKeyColumns
    {
        return new WikiExactKeyColumns(userIds: true, comments: true, favourites: true, recents: true, backlinks: true);
    }

    public function testItDeclaresEveryKeyColumnOfTheWikiTables(): void
    {
        $columns = $this->all()->exactKeyColumns();

        self::assertSame([
            'wiki_spaces.owner_scope_id',
            'wiki_page_links.object_type',
            'wiki_page_links.object_id',
            'wiki_page_favourites.user_id',
            'wiki_page_visits.user_id',
            'wiki_page_references.target_page_id',
            'wiki_page_revisions.author_id',
            'wiki_page_links.created_by_id',
            'wiki_attachments.uploaded_by_id',
            'wiki_comment_threads.created_by_id',
            'wiki_comment_threads.resolved_by_id',
            'wiki_comments.author_id',
            'wiki_comment_reactions.user_id',
        ], array_map(static fn (ExactKeyColumn $column): string => $column->describe(), $columns));

        foreach ($columns as $column) {
            self::assertTrue($column->active, $column->describe());
            self::assertNull($column->condition);
            self::assertSame(WikiExactKeyColumns::OWNER, $column->owner);
            self::assertNotSame('', $column->purpose, $column->describe());
        }
    }

    public function testEveryTableNameComesFromTheConfiguration(): void
    {
        $columns = (new WikiExactKeyColumns(
            spacesTableName: 'app_spaces',
            revisionsTableName: 'app_page_revisions',
            linksTableName: 'app_page_links',
            attachmentsTableName: 'app_attachments',
            commentTablePrefix: 'app',
            quickAccessTablePrefix: 'app',
            userIds: true,
            comments: true,
            favourites: true,
            recents: true,
            backlinks: true,
        ))->exactKeyColumns();

        foreach ($columns as $column) {
            self::assertTrue(str_starts_with($column->table, 'app_'), $column->describe());
        }
    }

    public function testTheOwnerAndHostObjectColumnsAreAlwaysActive(): void
    {
        // Default configuration: the space owner and the page links are exact whatever is switched off.
        foreach ((new WikiExactKeyColumns())->exactKeyColumns() as $column) {
            if ($column->table === 'wiki_spaces' || ($column->table === 'wiki_page_links' && $column->column !== 'created_by_id')) {
                self::assertTrue($column->active, $column->describe());
                self::assertNull($column->condition);
            }
        }
    }

    public function testTheUserIdColumnsAreInactiveWithTheEntityUserReference(): void
    {
        foreach ((new WikiExactKeyColumns(comments: true))->exactKeyColumns() as $column) {
            if (!in_array($column->column, ['author_id', 'created_by_id', 'resolved_by_id', 'uploaded_by_id'], true) && $column->table !== 'wiki_comment_reactions') {
                continue;
            }
            self::assertFalse($column->active, $column->describe());
            self::assertSame(WikiExactKeyColumns::USER_ID_CONDITION, $column->condition, $column->describe());
        }
    }

    public function testACommentColumnReportsTheSettingThatIsActuallyOff(): void
    {
        // Comments off, ids on: the comment tables do not exist at all, so that is what is reported.
        foreach ((new WikiExactKeyColumns(userIds: true, comments: false))->exactKeyColumns() as $column) {
            if (!str_starts_with($column->table, 'wiki_comment')) {
                continue;
            }
            self::assertFalse($column->active, $column->describe());
            self::assertSame(WikiExactKeyColumns::COMMENTS_CONDITION, $column->condition, $column->describe());
        }
    }

    public function testTheQuickAccessColumnsAreGatedOnTheirOwnSettings(): void
    {
        $conditions = [
            'wiki_page_favourites' => WikiExactKeyColumns::FAVOURITES_CONDITION,
            'wiki_page_visits'     => WikiExactKeyColumns::RECENTS_CONDITION,
            'wiki_page_references' => WikiExactKeyColumns::BACKLINKS_CONDITION,
        ];

        foreach ((new WikiExactKeyColumns())->exactKeyColumns() as $column) {
            if (!isset($conditions[$column->table])) {
                continue;
            }
            self::assertFalse($column->active, $column->describe());
            self::assertSame($conditions[$column->table], $column->condition, $column->describe());
        }

        foreach ((new WikiExactKeyColumns(favourites: true, recents: true, backlinks: true))->exactKeyColumns() as $column) {
            if (isset($conditions[$column->table])) {
                self::assertTrue($column->active, $column->describe());
            }
        }
    }

    public function testItIsSafeToCallMoreThanOnce(): void
    {
        // REQ-RUNTIME-002: nothing is memoised, so a worker that keeps the service gets the same answer every time.
        $declaration = $this->all();

        self::assertEquals($declaration->exactKeyColumns(), $declaration->exactKeyColumns());
    }
}
