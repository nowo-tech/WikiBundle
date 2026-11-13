<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function sprintf;

/**
 * {@see WikiPageLinkOperationsInterface} over {@see WikiPageLinkServiceInterface} (1.34.0).
 */
final readonly class WikiPageLinkOperations implements WikiPageLinkOperationsInterface
{
    public function __construct(
        private WikiPageLinkServiceInterface $links,
        private WikiPageRepositoryInterface $pages,
    ) {
    }

    public function linkPage(string $pageId, string $objectType, string $objectId, ?WikiUserRef $actor = null): void
    {
        $this->links->link($this->page($pageId), $objectType, $objectId, $actor instanceof WikiUserRef ? WikiUserRefs::object($actor) : null);
    }

    public function unlinkPage(string $pageId, string $objectType, string $objectId, ?WikiUserRef $actor = null): bool
    {
        return $this->links->unlink($this->page($pageId), $objectType, $objectId, $actor instanceof WikiUserRef ? WikiUserRefs::object($actor) : null);
    }

    public function usedIn(string $pageId, WikiUserRef $viewer): array
    {
        return $this->links->usedIn($this->page($pageId), WikiUserRefs::securityUser($viewer));
    }

    private function page(string $pageId): WikiPage
    {
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId) {
            throw new InvalidArgumentException(sprintf('Unknown wiki page "%s".', $pageId));
        }

        return $page;
    }
}
