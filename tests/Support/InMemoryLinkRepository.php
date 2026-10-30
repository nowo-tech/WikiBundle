<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Repository\WikiPageLinkRepositoryInterface;

/**
 * In-memory repository for unit tests.
 */
final class InMemoryLinkRepository implements WikiPageLinkRepositoryInterface
{
    /** @var list<WikiPageLink> */
    private array $links = [];

    public function save(WikiPageLink $link): void
    {
        $this->links[] = $link;
    }

    public function remove(WikiPageLink $link): void
    {
        $this->links = array_values(array_filter($this->links, static fn (WikiPageLink $l): bool => $l !== $link));
    }

    public function findOne(WikiPage $page, string $objectType, string $objectId): ?WikiPageLink
    {
        foreach ($this->links as $link) {
            if ($link->getPage() === $page && $link->getObjectType() === $objectType && $link->getObjectId() === $objectId) {
                return $link;
            }
        }

        return null;
    }

    public function findByHostObject(string $objectType, string $objectId): array
    {
        return [];
    }

    public function findByHostObjects(string $objectType, array $objectIds): array
    {
        return [];
    }

    public function findByPage(WikiPage $page): array
    {
        return array_values(array_filter($this->links, static fn (WikiPageLink $l): bool => $l->getPage() === $page));
    }
}
