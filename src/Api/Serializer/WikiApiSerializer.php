<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Serializer;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;

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
}
