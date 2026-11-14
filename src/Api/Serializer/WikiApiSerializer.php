<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Serializer;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\History\WikiActorLabels;

/**
 * 1.24 (spec 218): the API's bodies. Dates are Unix milliseconds, ids UUID strings, wikis named by slug.
 */
final readonly class WikiApiSerializer
{
    public static function ms(?DateTimeImmutable $date): ?int
    {
        return $date === null ? null : (int) $date->format('Uv');
    }

    /**
     * A wiki. One listed by name only (FR-011) shows its name and nothing about its content.
     *
     * @return array<string, mixed>
     */
    public function wiki(WikiApiWiki $wiki): array
    {
        $body = [
            'id'          => $wiki->space?->getId(),
            'slug'        => $wiki->slug,
            'name'        => $wiki->name,
            'description' => $wiki->description,
            'owner_type'  => $wiki->ownerType,
            'owner_id'    => $wiki->ownerId,
            'archived'    => $wiki->archived,
            'restricted'  => $wiki->restricted,
            'can_open'    => $wiki->canOpen,
            'created_at'  => self::ms($wiki->createdAt ?? $wiki->space?->getCreatedAt()),
            'updated_at'  => self::ms($wiki->updatedAt ?? $wiki->space?->getUpdatedAt()),
        ];
        if ($wiki->canOpen && $wiki->pageCount !== null) {
            $body['page_count'] = $wiki->pageCount;
        }

        return $body;
    }

    /**
     * A page's metadata: what lists show, and the head of a page body.
     *
     * Spec 234: `created_by` (the author of the page's oldest revision, presented like `updated_by`), and the
     * provenance of the last write — `channel` (web | app | api | mcp) and `token_name` (the host's actor label, plain
     * text, as {@see WikiActorLabels} allows: audit PQ-M1). All null when not recorded.
     *
     * @param array{id: string|null, name: string}|null $updatedBy
     * @param array{id: string|null, name: string}|null $createdBy
     *
     * @return array<string, mixed>
     */
    public function pageHead(WikiApiWiki $wiki, WikiPage $page, ?int $revision, ?DateTimeImmutable $revisedAt, ?array $updatedBy, ?array $createdBy = null, ?string $channel = null, ?string $tokenName = null): array
    {
        return [
            'id'         => $page->getId(),
            'wiki'       => $wiki->slug,
            'slug'       => $page->getSlug(),
            'title'      => $page->getTitle(),
            'parent'     => $page->getParent()?->getId(),
            'position'   => $page->getPosition(),
            'archived'   => $page->isArchived(),
            'revision'   => $revision,
            'created_at' => self::ms($page->getCreatedAt()),
            'updated_at' => self::ms($revisedAt ?? $page->getUpdatedAt()),
            'updated_by' => $updatedBy,
            'created_by' => $createdBy,
            'channel'    => $channel,
            'token_name' => $tokenName,
        ];
    }

    /**
     * A revision's head (the body, when asked, is added by the caller). Spec 234: `channel` and `token_name` of
     * the write (`author` is who made it); `token_name` as {@see WikiActorLabels} allows (the caller decides; audit PQ-M1).
     *
     * @param array{id: string|null, name: string}|null $author
     *
     * @return array<string, mixed>
     */
    public function revisionHead(WikiPageRevision $revision, ?array $author, ?string $label, ?string $tokenName = null): array
    {
        return [
            'number'     => $revision->getRevisionNumber(),
            'author'     => $author,
            'created_at' => self::ms($revision->getCreatedAt()),
            'label'      => $label,
            'format'     => $revision->getFormat(),
            'channel'    => $revision->getChannel(),
            'token_name' => $tokenName,
        ];
    }
}
