<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Error;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function property_exists;
use function sprintf;
use function trigger_deprecation;
use function trigger_error;

use const E_USER_WARNING;

/**
 * Dispatched before the page editor is built, so a host can add TiptapEditorType options (the suggestion hook's
 * candidate URLs, …) for this space and page (1.9). The bundle has already set the upload hook when attachments are
 * enabled.
 *
 * @property UserInterface $user the editor
 *                               (deprecated since 1.36, a runtime notice since 1.49, removed in 2.0): read
 *                               {@see self::userRef()}
 */
final class WikiEditorOptionsEvent
{
    /** The editor ({@see self::securityUser()}; `$event->user` is deprecated). */
    private readonly UserInterface $securityUser;

    /**
     * @param WikiSpace $space space of the page
     * @param WikiPage|null $page page being edited, or null for a new page
     * @param UserInterface $user editor; deprecated since 1.36 as a public member (a Symfony type), removed in 2.0: read
     *                            {@see self::userRef()}
     * @param array<string, mixed> $options TiptapEditorType options to merge
     */
    public function __construct(
        public readonly WikiSpace $space,
        public readonly ?WikiPage $page,
        UserInterface $user,
        public array $options = [],
    ) {
        $this->securityUser = $user;
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
     * 1.36: the editor as a framework-free reference (use it instead of {@see self::$user}).
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->securityUser);
    }

    /**
     * 1.49: `$event->user` still works and triggers a deprecation notice; 2.0 removes it.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'user') {
            trigger_deprecation('nowo-tech/wiki-bundle', '1.49', 'Reading %s::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a framework-free WikiUserRef) instead.', self::class);

            return $this->securityUser;
        }
        if (property_exists($this, $name)) {
            // A private member, refused from outside as before.
            throw new Error(sprintf('Cannot access private property %s::$%s', self::class, $name));
        }
        trigger_error(sprintf('Undefined property: %s::$%s', self::class, $name), E_USER_WARNING);

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'user';
    }

    /** `user` stays read-only, as it was (a readonly property up to 1.48). */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'user') {
            throw new Error(sprintf('Cannot modify readonly property %s::$user', self::class));
        }
        if (property_exists($this, $name)) {
            // A private member, refused from outside as before.
            throw new Error(sprintf('Cannot modify private property %s::$%s', self::class, $name));
        }
        // @igor-ignore - a per-dispatch event object (never a shared service): a dynamic property, as PHP allowed before 1.49
        $this->{$name} = $value;
    }

    /**
     * The user as the bundle works with it. Bundle use only: removed in 2.0 with {@see self::$user}.
     *
     * @internal
     */
    public function securityUser(): UserInterface
    {
        return $this->securityUser;
    }
}
