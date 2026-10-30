<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
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
     * @param UserInterface $user editor
     * @param array<string, mixed> $options TiptapEditorType options to merge
     */
    public function __construct(
        public readonly WikiSpace $space,
        public readonly ?WikiPage $page,
        public readonly UserInterface $user,
        public array $options = [],
    ) {
    }
}
