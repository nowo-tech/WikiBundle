<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Dispatched before the page editor is built, so a host can add TiptapEditorType options (the suggestion hook's
 * candidate URLs, …) for this space and page (1.9). The bundle has already set the upload hook when attachments are
 * enabled.
 */
final class WikiEditorOptionsEvent
{
    /**
     * @param WikiSpace $space space of the page
     * @param WikiPage|null $page page being edited, or null for a new page
     * @param UserInterface $user editor; deprecated since 1.34 as a public member (a Symfony type), removed in 2.0: read
     *                            {@see self::userRef()}
     * @param array<string, mixed> $options TiptapEditorType options to merge
     */
    public function __construct(
        public readonly WikiSpace $space,
        public readonly ?WikiPage $page,
        public readonly UserInterface $user,
        public array $options = [],
    ) {
    }

    /**
     * 1.34: the space's id.
     */
    public function spaceId(): string
    {
        return $this->space->getId();
    }

    /**
     * 1.34: the space as a framework-free snapshot.
     */
    public function spaceSnapshot(): WikiSpaceSnapshot
    {
        return WikiSnapshots::space($this->space);
    }

    /**
     * 1.34: the page's id; null for a new page.
     */
    public function pageId(): ?string
    {
        return $this->page?->getId();
    }

    /**
     * 1.34: the page as a snapshot; null for a new page.
     */
    public function pageSnapshot(): ?WikiPageSnapshot
    {
        return WikiSnapshots::pageOrNull($this->page);
    }

    /**
     * 1.34: the editor as a framework-free reference (use it instead of {@see self::$user}).
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->user);
    }
}
