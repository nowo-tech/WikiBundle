<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

use DateTimeImmutable;
use InvalidArgumentException;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218): a wiki as the host's directory describes it. `$space` is null for a wiki nobody opened yet (FR-009:
 * a read never creates it). Owner, description, archived and restricted are the host's notions; `$ownerKey` is what
 * {@see WikiApiWhere} matches.
 */
final readonly class WikiApiWiki
{
    /** 1.33: the id of the wiki's space (from `$space` when given); null while the space does not exist. */
    public ?string $spaceId;

    /**
     * @param string $slug the wiki's slug, its id in the API
     * @param WikiSpace|null $space the space, null when it does not exist yet
     * @param bool $canOpen false: listed by name only (a restricted wiki without a grant, FR-011); its pages are 404
     * @param int|null $pageCount pages the person can read, when the host counts them (never shown with `$canOpen` false)
     * @param string|null $spaceId 1.33: the space by id, for a framework-free directory
     *                             ({@see WikiApiWikiDirectoryInterface}) that holds no entity;
     *                             the bundle loads it. With `$space` it must be that space's id.
     *
     * @throws InvalidArgumentException when `$space` and `$spaceId` name different spaces
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
        ?string $spaceId = null,
    ) {
        $id = $space?->getId();
        if ($id !== null && $spaceId !== null && $id !== $spaceId) {
            throw new InvalidArgumentException('A wiki API wiki names one space: its space entity and its space id differ.');
        }
        $this->spaceId = $id ?? $spaceId;
    }

    /** The same wiki once its space exists (after the first write provisioned it). */
    public function withSpace(WikiSpace $space): self
    {
        return new self($this->slug, $space, $this->name, $this->description, $this->ownerType, $this->ownerId, $this->ownerKey, $this->archived, $this->restricted, $this->canOpen, $this->pageCount, $this->createdAt, $this->updatedAt);
    }
}
