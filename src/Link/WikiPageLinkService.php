<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use InvalidArgumentException;
use Nowo\WikiBundle\Doctrine\WikiManagedEntities;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Port\Internal\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Port\WikiObjectLinkSourceGate;
use Nowo\WikiBundle\Repository\WikiPageLinkRepositoryInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_keys;
use function array_map;
use function array_values;

/**
 * Links pages to host objects and lists, for a viewer, the objects a page is used in (1.9).
 *
 * Authorization of the link itself (who may link a page to an object) is the host's job: call
 * {@see self::link()} / {@see self::unlink()} only after checking it.
 */
final readonly class WikiPageLinkService implements WikiPageLinkServiceInterface
{
    /**
     * @param WikiPageLinkRepositoryInterface $linkRepository link persistence
     * @param iterable<object> $resolvers host resolvers (WikiObjectLinkResolverInterface, or since 1.36 WikiObjectLinkSourceInterface)
     * @param EventDispatcherInterface $eventDispatcher dispatcher of the link events
     * @param WikiUserReferenceResolverInterface|null $users 1.27: the stored link creator for a non-entity user
     * @param WikiManagedEntities|null $managed 1.55.2: the wiki manager's instance of a page another manager loaded
     */
    public function __construct(
        private WikiPageLinkRepositoryInterface $linkRepository,
        #[AutowireIterator('nowo_wiki.object_link_resolver')]
        private iterable $resolvers,
        private EventDispatcherInterface $eventDispatcher,
        private ?WikiUserReferenceResolverInterface $users = null,
        private ?WikiTransactionRunner $runner = null,
        private ?WikiManagedEntities $managed = null,
    ) {
    }

    /**
     * Links the page to the object; returns the existing link when there is one (idempotent).
     *
     * @param WikiPage $page page to link
     * @param string $objectType host object type
     * @param string $objectId host object id
     * @param object|null $actor user who links it
     *
     * @return WikiPageLink the link
     */
    public function link(WikiPage $page, string $objectType, string $objectId, ?object $actor = null): WikiPageLink
    {
        // 2.0 (W1-2): a wiki row your manager loaded is loaded again by id in the wiki's private manager.
        $page = $this->runner?->attach($page) ?? $page;
        // 1.37: looked up again under the page lock, so the same link sent twice at once is stored and announced once
        // (the second one used to fail on the unique key).
        [$link, $created] = $this->underPageLock($page, function () use ($page, $objectType, $objectId, $actor): array {
            $existing = $this->linkRepository->findOne($page, $objectType, $objectId);
            if ($existing instanceof WikiPageLink) {
                return [$existing, false];
            }

            $link = new WikiPageLink($page, $objectType, $objectId, $actor !== null ? ($this->users?->reference($actor) ?? $actor) : null);
            $this->linkRepository->save($link);

            return [$link, true];
        });
        if (!$created) {
            return $link;
        }
        $this->eventDispatcher->dispatch(new WikiPageLinkEvent($page, $objectType, $objectId, $actor), WikiEvents::PAGE_LINKED);

        return $link;
    }

    /**
     * Removes the link of the page to the object; returns false when there was none.
     *
     * @param WikiPage $page linked page
     * @param string $objectType host object type
     * @param string $objectId host object id
     * @param object|null $actor user who unlinks it
     *
     * @return bool whether a link was removed
     */
    public function unlink(WikiPage $page, string $objectType, string $objectId, ?object $actor = null): bool
    {
        // 2.0 (W1-2): a wiki row your manager loaded is loaded again by id in the wiki's private manager.
        $page    = $this->runner?->attach($page) ?? $page;
        $removed = $this->underPageLock($page, function () use ($page, $objectType, $objectId): bool {
            $link = $this->linkRepository->findOne($page, $objectType, $objectId);
            if (!$link instanceof WikiPageLink) {
                return false;
            }

            // @igor-ignore - Repository call (Doctrine remove + flush of a request-scoped entity); no service state.
            $this->linkRepository->remove($link);

            return true;
        });
        if (!$removed) {
            return false;
        }
        $this->eventDispatcher->dispatch(new WikiPageLinkEvent($page, $objectType, $objectId, $actor), WikiEvents::PAGE_UNLINKED);

        return true;
    }

    /**
     * The host objects the page is linked to that the viewer may open, grouped by type (one resolver call per type).
     *
     * @param WikiPage $page the page
     * @param UserInterface $viewer viewer
     *
     * @return array<string, list<WikiLinkedObject>> visible objects per type
     */
    public function usedIn(WikiPage $page, UserInterface $viewer): array
    {
        $idsByType = [];
        foreach ($this->linkRepository->findByPage($page) as $link) {
            $idsByType[$link->getObjectType()][$link->getObjectId()] = true;
        }

        $grouped = [];
        foreach ($idsByType as $type => $ids) {
            $resolver = $this->resolverFor($type);
            if (!$resolver instanceof WikiObjectLinkResolverInterface) {
                continue;
            }

            // Numeric ids become integer keys: hand the resolver strings, as the interface promises.
            $visible = $resolver->resolveVisible($viewer, $type, array_map(strval(...), array_keys($ids)));
            if ($visible !== []) {
                $grouped[$type] = array_values($visible);
            }
        }

        return $grouped;
    }

    /**
     * 1.37: a link write as one unit of work of the transaction runner, with the page row locked first (or as it is
     * without a runner). The event follows the commit.
     *
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     */
    private function underPageLock(WikiPage $page, callable $write): mixed
    {
        if (!$this->runner instanceof WikiTransactionRunner) {
            return $write();
        }
        $runner = $this->runner;

        // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
        return $runner->run(static function () use ($runner, $page, $write): mixed {
            // Audit QN-I2: a page purged meanwhile is not linked (nor unlinked).
            if (!$runner->lockRow(WikiPage::class, $page->getId())) {
                throw new InvalidArgumentException('The page no longer exists.');
            }

            return $write();
        });
    }

    private function resolverFor(string $type): ?WikiObjectLinkResolverInterface
    {
        foreach ($this->resolvers as $service) {
            // 1.36: a framework-free WikiObjectLinkSourceInterface is asked through its gate.
            $resolver = WikiObjectLinkSourceGate::of($service);
            if ($resolver instanceof WikiObjectLinkResolverInterface && $resolver->supports($type)) {
                return $resolver;
            }
        }

        return null;
    }
}
