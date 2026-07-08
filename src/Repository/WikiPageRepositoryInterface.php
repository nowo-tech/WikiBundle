<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

interface WikiPageRepositoryInterface
{
    public function save(WikiPage $page): void;

    public function findById(string $id): ?WikiPage;

    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage;

    /**
     * @return list<WikiPage>
     */
    public function findActiveBySpace(WikiSpace $space): array;

    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int;
}
