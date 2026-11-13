<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The page ↔ host object links by page id (1.34.0, REQ-ARCH-001 rule 1): the framework-free twin of
 * {@see WikiPageLinkServiceInterface} (public alias; same events). An unknown page id fails closed.
 */
interface WikiPageLinkOperationsInterface
{
    /** @throws InvalidArgumentException when the page is unknown */
    public function linkPage(string $pageId, string $objectType, string $objectId, ?WikiUserRef $actor = null): void;

    /**
     * @throws InvalidArgumentException when the page is unknown
     *
     * @return bool whether a link was removed
     */
    public function unlinkPage(string $pageId, string $objectType, string $objectId, ?WikiUserRef $actor = null): bool;

    /**
     * The linked host objects the viewer may open, grouped by type.
     *
     * @throws InvalidArgumentException when the page is unknown
     *
     * @return array<string, list<WikiLinkedObject>>
     */
    public function usedIn(string $pageId, WikiUserRef $viewer): array;
}
