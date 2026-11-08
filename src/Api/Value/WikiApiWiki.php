<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218): a wiki as the host's directory describes it. `$space` is null for a wiki nobody opened yet (FR-009:
 * a read never creates it). Owner, description, archived and restricted are the host's notions; `$ownerKey` is what
 * {@see WikiApiWhere} matches.
 */
final readonly class WikiApiWiki
{
    /**
     * @param string $slug the wiki's slug, its id in the API
     * @param WikiSpace|null $space the space, null when it does not exist yet
     * @param bool $canOpen false: listed by name only (a restricted wiki without a grant, FR-011); its pages are 404
     * @param int|null $pageCount pages the person can read, when the host counts them (never shown with `$canOpen` false)
     */
    public function __construct(
        public string $slug,
        public ?WikiSpace $space,
        public string $name,
        public ?string $description = null,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
        public ?string $ownerKey = null,
        public bool $archived = false,
        public bool $restricted = false,
        public bool $canOpen = true,
        public ?int $pageCount = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }

    /** The same wiki once its space exists (after the first write provisioned it). */
    public function withSpace(WikiSpace $space): self
    {
        return new self($this->slug, $space, $this->name, $this->description, $this->ownerType, $this->ownerId, $this->ownerKey, $this->archived, $this->restricted, $this->canOpen, $this->pageCount, $this->createdAt, $this->updatedAt);
    }
}
