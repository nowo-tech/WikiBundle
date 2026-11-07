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

    /** 1.14.1: updates in the log after the snapshot, kept with every write (no SUM over the log per request). */
    #[ORM\Column(name: 'update_count', type: 'integer', options: ['default' => 0])]
    private int $updateCount = 0;

    /** 1.14.1: bytes of the stored log (snapshot and updates, base64), kept with every write. */
    #[ORM\Column(name: 'log_bytes', type: 'integer', options: ['default' => 0])]
    private int $logBytes = 0;

    /** 1.14.1: sequence number of the last stored update. */
    #[ORM\Column(name: 'last_seq', type: 'integer', options: ['default' => 0])]
    private int $lastSeq = 0;

    /** 1.14.1: sequence number the snapshot covers. */
    #[ORM\Column(name: 'snapshot_seq', type: 'integer', options: ['default' => 0])]
    private int $snapshotSeq = 0;

    /** Sequence number when a live session last saved the page: later updates are not in any revision. */
    #[ORM\Column(name: 'saved_seq', type: 'integer', options: ['default' => 0])]
    private int $savedSeq = 0;

    /**
     * @param string $pageId id of the page (one document per page)
     * @param string|null $baseRevisionId revision the log started from or was last saved as
     * @param DateTimeImmutable|null $seededAt when a client was asked to seed the log
     * @param string|null $seededBy identifier of the user (and client) that seeds the log
     * @param int $recentResets resets after a client reported a corrupt log, in the current window
     * @param DateTimeImmutable|null $lastResetAt last such reset
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'page_id', type: 'string', length: 36)]
        private string $pageId,
        #[ORM\Column(name: 'base_revision_id', type: 'string', length: 36, nullable: true)]
        private ?string $baseRevisionId = null,
        #[ORM\Column(name: 'seeded_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $seededAt = null,
        #[ORM\Column(name: 'seeded_by', type: 'string', length: 180, nullable: true)]
        private ?string $seededBy = null,
        #[ORM\Column(name: 'recent_resets', type: 'integer', options: ['default' => 0])]
        private int $recentResets = 0,
        #[ORM\Column(name: 'last_reset_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $lastResetAt = null,
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

    public function getSeededBy(): ?string
    {
        return $this->seededBy;
    }

    public function getRecentResets(): int
    {
        return $this->recentResets;
    }

    public function getLastResetAt(): ?DateTimeImmutable
    {
        return $this->lastResetAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdateCount(): int
    {
        return $this->updateCount;
    }

    public function getLogBytes(): int
    {
        return $this->logBytes;
    }

    public function getLastSeq(): int
    {
        return $this->lastSeq;
    }

    public function getSavedSeq(): int
    {
        return $this->savedSeq;
    }

    public function getSnapshotSeq(): int
    {
        return $this->snapshotSeq;
    }
}
