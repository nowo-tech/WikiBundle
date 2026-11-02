<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageService;

/**
 * Page repository whose findById() first saves a new revision of the page, as if someone edited it while
 * wiki:convert-markdown was running (CV-I7).
 */
final readonly class ConcurrentlyEditedPageRepository implements WikiPageRepositoryInterface
{
    public function __construct(
        private WikiPageRepositoryInterface $inner,
        private WikiPageService $service,
        private object $author,
    ) {
    }

    public function save(WikiPage $page): void
    {
        $this->inner->save($page);
    }

    public function findById(string $id): ?WikiPage
    {
        $page = $this->inner->findById($id);
        if ($page instanceof WikiPage) {
            $this->service->saveRevision($page, $page->getTitle(), '<p>edited meanwhile</p>', $this->author);
        }

        return $page;
    }

    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
    {
        return $this->inner->findBySlug($space, $slug);
    }

    public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
    {
        return $this->inner->findActiveBySpace($space, $limit);
    }

    public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
    {
        return $this->inner->findArchivedBySpace($space, $limit);
    }

    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
    {
        return $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
    }
}
