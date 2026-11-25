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
     * The user, read with {@see self::userRef()}. 2.0: private (the 1.x public `$user` was removed), so a host that
     * still reads `->user` gets an Error instead of a silent null.
     */
    private readonly UserInterface $user;

    /**
     * @param WikiSpace $space space of the page
     * @param WikiPage|null $page page being edited, or null for a new page
     * @param UserInterface $user editor (2.0: no longer a public member; read {@see self::userRef()})
     * @param array<string, mixed> $options TiptapEditorType options to merge
     */
    public function __construct(
        public readonly WikiSpace $space,
        public readonly ?WikiPage $page,
        UserInterface $user,
        public array $options = [],
    ) {
        $this->user = $user;
    }

    /**
     * 1.36: the space's id.
     */
    public function spaceId(): string
    {
        return $this->space->getId();
    }

    /**
     * 1.36: the space as a framework-free snapshot.
     */
    public function spaceSnapshot(): WikiSpaceSnapshot
    {
        return WikiSnapshots::space($this->space);
    }

    /**
     * 1.36: the page's id; null for a new page.
     */
    public function pageId(): ?string
    {
        return $this->page?->getId();
    }

    /**
     * 1.36: the page as a snapshot; null for a new page.
     */
    public function pageSnapshot(): ?WikiPageSnapshot
    {
        return WikiSnapshots::pageOrNull($this->page);
    }

    /**
     * 1.36: the editor as a framework-free reference.
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->user);
    }
}
