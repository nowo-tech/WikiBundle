<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumnsInterface;

/**
 * The bundle's own declaration of the identity / access-key columns of its tables (REQ-SEC-006).
 *
 * The bundle already keeps these columns on the server's binary NO PAD collation when the schema is generated
 * ({@see WikiSpaceCollationListener}): the owner of a space since 1.31.1 (GHSA-xpr3-grw5-6h9c), the host object a
 * page is linked to since 1.37, the person of a favourite or a visit and the target of a backlink row since 1.45, and
 * every user id column of `database.user_reference: id` since 1.36. On a folding collation `Alice`, `álice` and
 * `alice ` are one owner — so a space of scope `alice` is reachable from scope `Alice`, and the
 * `(owner_scope_type, owner_scope_id, slug)` unique key stops separating two owners.
 *
 * What was missing is the *assertion*. Until now only a host's hand-kept list knew that `wiki_spaces.owner_scope_id`
 * is an access key, so a table created before 1.31.1, restored from a dump or imported by hand kept the host's
 * folding default and nothing said so. This declaration is what `nowo:migrations:check-key-collations` checks;
 * MigrationsKit discovers it by autoconfiguration, so a host registers **nothing** and can delete the
 * `nowo_migrations_kit.collation_check.ignore_columns` entries it kept for these columns.
 *
 * Every table name is **configurable**, so each one is built from the same configuration the schema is built from
 * (`database.spaces_table`, the shared table prefix, the comment and quick-access prefixes): there is not one
 * literal table name here. The columns of a feature that is off — comments, favourites, recents, the backlink index
 * — and the user id columns of the `entity` user reference (where those columns are foreign keys to the host's users
 * table, not ids) are declared **inactive** with the setting that gates them: reported as skipped, never as a missing
 * table.
 *
 * Deliberately **not** declared, because a declaration is a claim about identity and an untrue one is worse than
 * none (on a server with no NO PAD collation it reports `fallback` about a column that does not want NO PAD):
 *
 * - `wiki_spaces.slug` and `wiki_pages.slug`: a slug is a human URL name, matched the way the application wants to
 *   match names, not an identity compared byte for byte.
 * - `wiki_spaces.owner_scope_type`: the enum half of the owner (`organization`, `user`, …), a value the bundle
 *   itself writes from a PHP enum, not something a visitor can make look alike.
 * - Titles, bodies, labels and search excerpts, the timestamps and the state columns.
 * - The uuid primary keys and the foreign keys between wiki tables, which are generated ids with no collation
 *   question of their own (`CHAR(36)` uuids are matched by the uuid itself, and the schema's own keys enforce them).
 *
 * Stateless and database-free (REQ-RUNTIME-002): the list is rebuilt on every call, so the service is safe to keep
 * between requests in a FrankenPHP worker with `kernel.reset: false`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WikiExactKeyColumns implements ExactKeyColumnsInterface
{
    /** The package that owns the tables, as reported by the aggregate run. */
    public const OWNER = 'nowo-tech/wiki-bundle';

    /** Condition reported for the user id columns with the `entity` user reference. */
    public const USER_ID_CONDITION = 'nowo_wiki.database.user_reference: id';

    /** Condition reported for the comment tables when comments are off. */
    public const COMMENTS_CONDITION = 'nowo_wiki.comments.enabled: true';

    /** Condition reported for the favourites table when favourites are off. */
    public const FAVOURITES_CONDITION = 'nowo_wiki.favourites.enabled: true';

    /** Condition reported for the visits table when recents are off. */
    public const RECENTS_CONDITION = 'nowo_wiki.recents.enabled: true';

    /** Condition reported for the backlink index when it is off. */
    public const BACKLINKS_CONDITION = 'nowo_wiki.backlinks.enabled: true';

    /**
     * @param string $spacesTableName the configured spaces table
     * @param string $revisionsTableName the configured revisions table
     * @param string $linksTableName the configured page links table
     * @param string $attachmentsTableName the configured attachments table
     * @param string $commentTablePrefix prefix of `<prefix>_comment_threads`, `_comments`, `_comment_reactions`
     * @param string $quickAccessTablePrefix prefix of `<prefix>_page_favourites`, `_page_visits`, `_page_references`
     * @param bool $userIds nowo_wiki.database.user_reference is `id` (the user columns hold ids, not foreign keys)
     * @param bool $comments nowo_wiki.comments.enabled
     * @param bool $favourites nowo_wiki.favourites.enabled
     * @param bool $recents nowo_wiki.recents.enabled
     * @param bool $backlinks nowo_wiki.backlinks.enabled
     */
    public function __construct(
        private string $spacesTableName = 'wiki_spaces',
        private string $revisionsTableName = 'wiki_page_revisions',
        private string $linksTableName = 'wiki_page_links',
        private string $attachmentsTableName = 'wiki_attachments',
        private string $commentTablePrefix = 'wiki',
        private string $quickAccessTablePrefix = 'wiki',
        private bool $userIds = false,
        private bool $comments = true,
        private bool $favourites = false,
        private bool $recents = false,
        private bool $backlinks = false,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function exactKeyColumns(): array
    {
        $threads   = $this->commentTablePrefix . '_comment_threads';
        $comments  = $this->commentTablePrefix . '_comments';
        $reactions = $this->commentTablePrefix . '_comment_reactions';

        $userIds = $this->userIds ? null : self::USER_ID_CONDITION;
        // Two settings gate a comment table's user id column: report the one that is actually off.
        $commentUsers    = $this->comments && $this->userIds;
        $commentUsersWhy = $commentUsers ? null : ($this->comments ? self::USER_ID_CONDITION : self::COMMENTS_CONDITION);

        return [
            new ExactKeyColumn($this->spacesTableName, 'owner_scope_id', self::OWNER, 'owner of the space: the key its access is decided by, and part of its unique key', null, true),
            new ExactKeyColumn($this->linksTableName, 'object_type', self::OWNER, 'kind of the host object a page is linked to', null, true),
            new ExactKeyColumn($this->linksTableName, 'object_id', self::OWNER, 'id of the host object a page is linked to (the task ABC is not the task abc)', null, true),
            new ExactKeyColumn($this->quickAccessTablePrefix . '_page_favourites', 'user_id', self::OWNER, 'person whose favourites these are', $this->favourites ? null : self::FAVOURITES_CONDITION, $this->favourites),
            new ExactKeyColumn($this->quickAccessTablePrefix . '_page_visits', 'user_id', self::OWNER, 'person whose recently visited pages these are', $this->recents ? null : self::RECENTS_CONDITION, $this->recents),
            new ExactKeyColumn($this->quickAccessTablePrefix . '_page_references', 'target_page_id', self::OWNER, 'page a backlink index row points at', $this->backlinks ? null : self::BACKLINKS_CONDITION, $this->backlinks),
            new ExactKeyColumn($this->revisionsTableName, 'author_id', self::OWNER, 'author of the revision (host user id)', $userIds, $this->userIds),
            new ExactKeyColumn($this->linksTableName, 'created_by_id', self::OWNER, 'person who linked the page (host user id)', $userIds, $this->userIds),
            new ExactKeyColumn($this->attachmentsTableName, 'uploaded_by_id', self::OWNER, 'person who uploaded the attachment (host user id)', $userIds, $this->userIds),
            new ExactKeyColumn($threads, 'created_by_id', self::OWNER, 'person who started the comment thread (host user id)', $commentUsersWhy, $commentUsers),
            new ExactKeyColumn($threads, 'resolved_by_id', self::OWNER, 'person who resolved the comment thread (host user id)', $commentUsersWhy, $commentUsers),
            new ExactKeyColumn($comments, 'author_id', self::OWNER, 'author of the comment (host user id)', $commentUsersWhy, $commentUsers),
            new ExactKeyColumn($reactions, 'user_id', self::OWNER, 'person who reacted: one reaction per person and emoji', $commentUsersWhy, $commentUsers),
        ];
    }
}
