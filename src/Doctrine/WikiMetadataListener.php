<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;

use function array_replace_recursive;
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
     */
    public function __construct(
        private string $spacesTableName,
        private string $pagesTableName,
        private string $revisionsTableName,
        private string $userClass,
        private string $linksTableName = 'wiki_page_links',
        private string $attachmentsTableName = 'wiki_attachments',
    ) {
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

        if ($class === WikiAttachment::class) {
            $metadata->setPrimaryTable(array_merge($metadata->table, ['name' => $this->attachmentsTableName]));
            $this->remapAuthorAssociation($metadata, 'uploadedBy');
        }
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
