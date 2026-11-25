<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Doctrine\WikiManagedEntities;
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
     * @param object|null $emojiCatalog 1.39: IconSelector's `EmojiCatalogInterface` (`page_icon.emoji`): an `emoji:`
     *                                  value must be exactly one RGI emoji of it; without it no emoji is accepted
     * @param WikiManagedEntities|null $managed 1.55.2: the wiki manager's instance of a page another manager loaded
     */
    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES,
        private ?WikiTransactionRunner $runner = null,
        private ?object $iconRenderer = null,
        private ?object $emojiCatalog = null,
        private ?WikiManagedEntities $managed = null,
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
     * Whether the wiki's connection already has a transaction open (the page form's save runs inside the co-editing
     * log's lock, 1.38.1): a transient failure of {@see self::setIcon()} is then the failure of that whole unit.
     */
    public function isInTransaction(): bool
    {
        return $this->runner?->manager()->getConnection()->isTransactionActive() ?? false;
    }

    /**
     * Normalises an icon value: '' is no icon (null). Throws when the value is not acceptable.
     *
     * @throws WikiInvalidIconException when the value is not an allowed icon name (an {@see \InvalidArgumentException}, 1.40.2)
     */
    public function normalize(?string $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return null;
        }
        if (!self::acceptable($icon, $this->allowedPrefixes, $this->iconRenderer, $this->emojiCatalog)) {
            throw new WikiInvalidIconException(sprintf('Invalid page icon: expected "<prefix>:<name>" (max %d characters) with a prefix in [%s] and an icon that exists.', WikiSpaceIcon::MAX_LENGTH, implode(', ', $this->allowedPrefixes)));
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
        } catch (WikiInvalidIconException) {
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
     * @throws WikiInvalidIconException when the icon is not allowed (nothing written)
     * @throws WikiPageArchivedException when the page is archived (nothing written)
     * @throws WikiPageNotFoundException when the page no longer exists (nothing written)
     * @throws TransientStorageFailure when the page row could not be locked or written (a deadlock, a lock wait timeout)
     *
     * @return bool whether the stored value changed
     */
    public function setIcon(WikiPage $page, ?string $icon): bool
    {
        // 2.0 (W1-2): a wiki row your manager loaded is loaded again by id in the wiki's private manager.
        $page  = $this->runner?->attach($page) ?? $page;
        $icon  = $this->normalize($icon);
        $apply = function () use ($page, $icon): bool {
            if ($page->isArchived()) {
                throw new WikiPageArchivedException('Archived pages cannot be edited.');
            }
            if ($page->getIcon() === $icon) {
                return false;
            }
            // @igor-ignore - a wiki row of this call (loaded by the private manager), never a shared service.
            $page->setIcon($icon, $this->allowedPrefixes);
            $this->pages->save($page);

            return true;
        };
        if (!$this->runner instanceof WikiTransactionRunner) {
            return $apply();
        }
        $runner = $this->runner;

        // Same as archiving: the page row locked and read again, so a page archived meanwhile is refused.
        return $runner->run(static fn (): bool => $runner->lockEntity($page) ? $apply() : throw new WikiPageNotFoundException('The page no longer exists.'));
    }

    /**
     * Sets or clears the icon of the page with exactly that id (the by-id twin of {@see self::setIcon()}).
     *
     * @throws WikiPageNotFoundException when no page has that id
     * @throws WikiInvalidIconException when the icon is not allowed
     * @throws WikiPageArchivedException when the page is archived
     */
    public function setPageIcon(string $pageId, ?string $icon): WikiPageSnapshot
    {
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId) {
            throw new WikiPageNotFoundException(sprintf('Unknown page "%s".', $pageId));
        }
        $this->setIcon($page, $icon);

        return WikiSnapshots::page($page);
    }

    /**
     * The one rule of every icon writer (this service, the page and space form fields): the format and prefixes
     * ({@see WikiSpaceIcon::isValid()}); for an icon, the optional UX Icons renderer must know it; for an emoji (1.39),
     * the IconSelector catalogue must report exactly one RGI emoji (no catalogue: refused).
     *
     * @param list<string> $allowedPrefixes accepted prefixes (with "emoji" when emoji are allowed)
     * @param object|null $iconRenderer optional Symfony UX Icons `IconRendererInterface`
     * @param object|null $emojiCatalog optional IconSelector `EmojiCatalogInterface`
     */
    public static function acceptable(string $icon, array $allowedPrefixes, ?object $iconRenderer, ?object $emojiCatalog): bool
    {
        if (!WikiSpaceIcon::isValid($icon, $allowedPrefixes)) {
            return false;
        }
        if (WikiSpaceIcon::isEmoji($icon)) {
            $emoji = WikiSpaceIcon::emojiOf($icon);

            return $emoji !== null && $emojiCatalog !== null && method_exists($emojiCatalog, 'isValid') && $emojiCatalog->isValid($emoji) === true;
        }
        if ($iconRenderer === null || !method_exists($iconRenderer, 'renderIcon')) {
            return true;
        }
        try {
            $iconRenderer->renderIcon($icon);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
