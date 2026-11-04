<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;

use function array_replace_recursive;
use function array_reverse;
use function array_values;
use function ltrim;

/**
 * Applies configurable table names and user entity mapping to wiki entities.
 */
final readonly class WikiMetadataListener
{
    /**
     * Stores the configured table names and the application user class.
     *
     * @param string $spacesTableName table name for wiki spaces
     * @param string $pagesTableName table name for wiki pages
     * @param string $revisionsTableName table name for wiki page revisions
     * @param string $userClass entity class targeted by the revision author association
     * @param string $linksTableName table name for page links (1.9)
     * @param string $attachmentsTableName table name for attachments (1.9)
     * @param string $collabDocumentsTableName table name for collaboration documents (1.13)
     * @param string $collabUpdatesTableName table name for the collaboration Yjs log (1.13)
     * @param bool $collaborationEnabled false: the two collaboration tables are left out of the generated schema (1.13.1)
     * @param string $trashTableName table name for the trash (1.14)
     * @param bool $trashEnabled false: the trash table is left out of the generated schema (1.14)
     * @param string $revisionLabelsTableName table name for the named versions (1.15)
     * @param bool $historyTimeline false: the named-version table is left out of the generated schema (1.15)
     * @param string $commentTablePrefix prefix of the three comment tables `<prefix>_comment_threads`, `_comments`,
     *                                   `_comment_reactions` (1.16)
     * @param bool $commentsEnabled false: the comment tables are left out of the generated schema (1.16)
     */
    public function __construct(
        private string $spacesTableName,
        private string $pagesTableName,
        private string $revisionsTableName,
        private string $userClass,
        private string $linksTableName = 'wiki_page_links',
        private string $attachmentsTableName = 'wiki_attachments',
        private string $collabDocumentsTableName = 'wiki_page_collab_documents',
        private string $collabUpdatesTableName = 'wiki_page_collab_updates',
        private bool $collaborationEnabled = true,
        private string $trashTableName = 'wiki_page_trash',
        private bool $trashEnabled = true,
        private string $revisionLabelsTableName = 'wiki_revision_labels',
        private bool $historyTimeline = true,
        private string $commentTablePrefix = 'wiki',
        private bool $commentsEnabled = true,
    ) {
    }

    /**
     * Without the trash (`trash.enabled: false`, 1.14) its table is not part of the schema either. Without co-editing
     * (`collaboration.enabled: false`) the collaboration tables are not part of the schema, so
     * `doctrine:schema:update` and `doctrine:migrations:diff` do not ask for them (1.13.1). The entities stay mapped (no
     * service uses them then); a host enabling co-editing later gets the two tables in its next diff.
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        // Only the wiki's entity manager (where the wiki entities are mapped), only the tables of disabled features.
        if ($args->getEntityManager()->getMetadataFactory()->isTransient(WikiCollabDocument::class)) {
            return;
        }
        $tables = $this->collaborationEnabled ? [] : [$this->collabUpdatesTableName, $this->collabDocumentsTableName];
        if (!$this->trashEnabled) {
            $tables[] = $this->trashTableName;
        }
        if (!$this->historyTimeline) {
            $tables[] = $this->revisionLabelsTableName;
        }
        if (!$this->commentsEnabled) {
            // Children first: a platform that drops foreign keys with the table never sees a dangling one.
            $tables = [...$tables, ...array_reverse(array_values($this->commentTables()))];
        }
        $schema = $args->getSchema();
        foreach ($tables as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }

    /**
     * Applies the configured table names to the wiki entities and remaps the revision author to the user class.
     *
     * @param LoadClassMetadataEventArgs $args Doctrine metadata event arguments
     */
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

        if ($class === WikiPageLink::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->linksTableName]));
            $this->remapAuthorAssociation($metadata, 'createdBy');

            return;
        }

        if ($class === WikiCollabDocument::class || $class === WikiCollabUpdate::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $class === WikiCollabDocument::class ? $this->collabDocumentsTableName : $this->collabUpdatesTableName]));

            return;
        }

        if ($class === WikiRevisionLabel::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->revisionLabelsTableName]));

            return;
        }

        if (isset($this->commentTables()[$class])) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->commentTables()[$class]]));
            foreach (['createdBy', 'resolvedBy', 'author', 'user'] as $field) {
                $this->remapAuthorAssociation($metadata, $field);
            }

            return;
        }

        if ($class === WikiTrashEntry::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->trashTableName]));

            return;
        }

        if ($class === WikiAttachment::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->attachmentsTableName]));
            $this->remapAuthorAssociation($metadata, 'uploadedBy');
        }
    }

    /**
     * The comment tables (1.16) by entity class, parents first.
     *
     * @return array<class-string, string>
     */
    private function commentTables(): array
    {
        return [
            WikiCommentThread::class   => $this->commentTablePrefix . '_comment_threads',
            WikiComment::class         => $this->commentTablePrefix . '_comments',
            WikiCommentReaction::class => $this->commentTablePrefix . '_comment_reactions',
        ];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function remapAuthorAssociation(ClassMetadata $metadata, string $field = 'author'): void
    {
        if (!isset($metadata->associationMappings[$field])) {
            return;
        }

        $mapping      = $metadata->associationMappings[$field];
        $targetEntity = ltrim($this->userClass, '\\');

        // doctrine/orm ^3 stores association mappings as AssociationMapping objects only.
        $newMapping              = array_replace_recursive($mapping->toArray(), ['targetEntity' => $targetEntity]);
        $newMapping['fieldName'] = $mapping->fieldName;
        unset($metadata->associationMappings[$field]);
        $metadata->mapManyToOne($newMapping);
    }
}
