<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageService;

use function sprintf;

/**
 * 1.55.2 (Beacon, `database.private_entity_manager: true`): the instance of a space or page that the wiki's own entity
 * manager holds, for a write that attaches it to a new wiki row or locks, refreshes or saves it.
 *
 * A space or page the host hands over (a deprecated host port such as `WikiApiDirectoryInterface::provision()` or
 * `WikiSpaceLocatorInterface::locate()`, or host code calling {@see WikiPageService}) may be an
 * instance of the application's manager. With the private manager that instance is unknown to the wiki's unit of work:
 * Doctrine reports it as "a new entity found through WikiPage#space" when its row is not visible to the wiki yet, and a
 * page lock answers "entity is not managed". Every such entry point asks this class, which answers:
 *
 * - the instance itself when the wiki's manager already holds it (always the case with the shared manager, where the
 *   host and the wiki use the same manager: nothing changes there);
 * - otherwise the wiki manager's instance of the same id, read through the identity map or loaded once (never a
 *   refresh: the write paths lock and read the row again under their lock);
 * - a failure when no row of that id is visible to the wiki's connection (fail closed: nothing was written).
 *
 * The caller's instance is never written, merged or detached; the write goes to the returned one.
 *
 * @internal
 */
final readonly class WikiManagedEntities
{
    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki's manager, or the provider that
     *                                                                        resolves it on every call
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    /**
     * The wiki manager's instance of a space the caller loaded.
     *
     * @throws LogicException when no row of that space is visible to the wiki (e.g. a host port persisted it in the
     *                        application's manager without flushing it): nothing was written
     */
    public function space(WikiSpace $space): WikiSpace
    {
        $manager = $this->manager();
        if ($manager->contains($space)) {
            return $space;
        }

        return $this->spaceById($space->getId()) ?? throw new LogicException(sprintf('The wiki space "%s" handed to the wiki is not stored yet: with nowo_wiki.database.private_entity_manager the wiki reads it through its own entity manager, so the code that created it must flush it (and commit it, on another connection) before handing it over. See UPGRADING "1.55.2".', $space->getId()));
    }

    /**
     * The wiki manager's instance of a space id (identity map first, else one load); null when no row matches exactly.
     */
    public function spaceById(string $id): ?WikiSpace
    {
        $space = $this->manager()->find(WikiSpace::class, $id);

        return $space instanceof WikiSpace && $space->getId() === $id ? $space : null;
    }

    /**
     * The wiki manager's instance of a page the caller loaded.
     *
     * @throws WikiPageNotFoundException when no row of that page is visible to the wiki: nothing was written
     */
    public function page(WikiPage $page): WikiPage
    {
        $manager = $this->manager();
        if ($manager->contains($page)) {
            return $page;
        }
        $managed = $manager->find(WikiPage::class, $page->getId());

        return $managed instanceof WikiPage && $managed->getId() === $page->getId() ? $managed : throw new WikiPageNotFoundException('Page not found.');
    }

    private function manager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
