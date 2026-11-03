<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;

/**
 * One page in the trash (1.14, T065, `trash.enabled`): a deleted page and its sub-pages share a batch; the page the
 * user deleted is the batch root. A trashed page is also archived (hidden from every read path); `wasArchived`
 * remembers pages that were archived before, which a restore leaves archived. Purging deletes the pages for good.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_trash')]
#[ORM\UniqueConstraint(name: 'wiki_page_trash_page', columns: ['page_id'])]
#[ORM\Index(name: 'wiki_page_trash_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'wiki_page_trash_deleted', columns: ['deleted_at'])]
class WikiTrashEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    /**
     * @param WikiPage $page trashed page
     * @param string $batchId the deletion it belongs to (the root and its sub-pages)
     * @param bool $root whether the user deleted this page (the others followed it)
     * @param bool $wasArchived the page was archived before the deletion (a restore keeps it archived)
     * @param DateTimeImmutable $deletedAt when it was deleted
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\Column(name: 'batch_id', type: 'string', length: 36)]
        private string $batchId,
        #[ORM\Column(name: 'is_root', type: 'boolean')]
        private bool $root,
        #[ORM\Column(name: 'was_archived', type: 'boolean')]
        private bool $wasArchived,
        #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable')]
        private DateTimeImmutable $deletedAt,
    ) {
        $this->id = Uuid::generate()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    public function wasArchived(): bool
    {
        return $this->wasArchived;
    }

    public function getDeletedAt(): DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
