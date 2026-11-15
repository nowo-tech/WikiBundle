<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Throwable;

use function sprintf;

/**
 * The optional icon of a page (1.38): the space icon's format (`<prefix>:<name>`, a prefix of
 * `space_icon.allowed_prefixes`, at most 64 characters), checked here for every writer (page form, REST API, import)
 * and, with Symfony UX Icons installed, also refused when the icon set has no such icon (a missing icon would make
 * `ux_icon()` throw on every page that shows it).
 *
 * The page row is locked and read again in one unit of work of the bundle's runner (docs/HIGH-LOAD.md): an archived
 * page is refused, the same value writes nothing, the last write wins. The icon is not content: no revision is added
 * and no page event is dispatched. Stateless (worker safe).
 */
final readonly class WikiPageIconService
{
    /**
     * @param WikiPageRepositoryInterface $pages page persistence
     * @param list<string> $allowedPrefixes `space_icon.allowed_prefixes`
     * @param WikiTransactionRunner|null $runner the bundle's runner (null: the plain write, tests)
     * @param object|null $iconRenderer optional Symfony UX Icons `IconRendererInterface`
     */
    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES,
        private ?WikiTransactionRunner $runner = null,
        private ?object $iconRenderer = null,
    ) {
    }

    /**
     * The accepted icon set prefixes.
     *
     * @return list<string>
     */
    public function allowedPrefixes(): array
    {
        return $this->allowedPrefixes;
    }

    /**
     * Normalises an icon value: '' is no icon (null). Throws when the value is not acceptable.
     *
     * @throws InvalidArgumentException when the value is not an allowed icon name
     */
    public function normalize(?string $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return null;
        }
        if (!WikiSpaceIcon::isValid($icon, $this->allowedPrefixes) || !$this->exists($icon)) {
            throw new InvalidArgumentException(sprintf('Invalid page icon: expected "<prefix>:<name>" (max %d characters) with a prefix in [%s] and an icon that exists.', WikiSpaceIcon::MAX_LENGTH, implode(', ', $this->allowedPrefixes)));
        }

        return $icon;
    }

    /**
     * Tells whether a value is an acceptable icon ('' and null are: they clear it).
     */
    public function isAllowed(?string $icon): bool
    {
        try {
            $this->normalize($icon);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Sets or clears the icon of a page.
     *
     * @param WikiPage $page the page
     * @param string|null $icon the icon name, or null / '' for none
     *
     * @throws InvalidArgumentException when the icon is not allowed or the page is archived (nothing written)
     *
     * @return bool whether the stored value changed
     */
    public function setIcon(WikiPage $page, ?string $icon): bool
    {
        $icon  = $this->normalize($icon);
        $apply = function () use ($page, $icon): bool {
            if ($page->isArchived()) {
                throw new InvalidArgumentException('Archived pages cannot be edited.');
            }
            if ($page->getIcon() === $icon) {
                return false;
            }
            $page->setIcon($icon, $this->allowedPrefixes);
            $this->pages->save($page);

            return true;
        };
        if (!$this->runner instanceof WikiTransactionRunner) {
            return $apply();
        }
        $runner = $this->runner;

        // Same as archiving: the page row locked and read again, so a page archived meanwhile is refused.
        return $runner->run(static function () use ($runner, $page, $apply): bool {
            if (!$runner->lockEntity($page)) {
                throw new InvalidArgumentException('The page no longer exists.');
            }

            return $apply();
        });
    }

    /**
     * Sets or clears the icon of the page with exactly that id (the by-id twin of {@see self::setIcon()}).
     *
     * @throws InvalidArgumentException when no page has that id, the icon is not allowed or the page is archived
     */
    public function setPageIcon(string $pageId, ?string $icon): WikiPageSnapshot
    {
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId) {
            throw new InvalidArgumentException(sprintf('Unknown page "%s".', $pageId));
        }
        $this->setIcon($page, $icon);

        return WikiSnapshots::page($page);
    }

    /**
     * Whether the optional UX Icons renderer knows the icon (true without a renderer).
     */
    private function exists(string $icon): bool
    {
        if ($this->iconRenderer === null || !method_exists($this->iconRenderer, 'renderIcon')) {
            return true;
        }
        try {
            $this->iconRenderer->renderIcon($icon);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
