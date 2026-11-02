<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Collaboration state of one page (1.13): which revision the stored Yjs log started from and whether a client was
 * already asked to seed it. The Yjs updates themselves are {@see WikiCollabUpdate} rows.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_collab_documents')]
class WikiCollabDocument
{
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param string $pageId id of the page (one document per page)
     * @param string|null $baseRevisionId revision the log started from or was last saved as
     * @param DateTimeImmutable|null $seededAt when a client was asked to seed the log
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'page_id', type: 'string', length: 36)]
        private string $pageId,
        #[ORM\Column(name: 'base_revision_id', type: 'string', length: 36, nullable: true)]
        private ?string $baseRevisionId = null,
        #[ORM\Column(name: 'seeded_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $seededAt = null,
    ) {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getPageId(): string
    {
        return $this->pageId;
    }

    public function getBaseRevisionId(): ?string
    {
        return $this->baseRevisionId;
    }

    public function getSeededAt(): ?DateTimeImmutable
    {
        return $this->seededAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
