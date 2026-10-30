<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Repository\WikiPageLinkRepositoryInterface;
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
final readonly class WikiPageLinkService
{
    /**
     * @param WikiPageLinkRepositoryInterface $linkRepository link persistence
     * @param iterable<WikiObjectLinkResolverInterface> $resolvers host resolvers
     * @param EventDispatcherInterface $eventDispatcher dispatcher of the link events
     */
    public function __construct(
        private WikiPageLinkRepositoryInterface $linkRepository,
        #[AutowireIterator('nowo_wiki.object_link_resolver')]
        private iterable $resolvers,
        private EventDispatcherInterface $eventDispatcher,
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
        $existing = $this->linkRepository->findOne($page, $objectType, $objectId);
        if ($existing instanceof WikiPageLink) {
            return $existing;
        }

        $link = new WikiPageLink($page, $objectType, $objectId, $actor);
        $this->linkRepository->save($link);
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
        $link = $this->linkRepository->findOne($page, $objectType, $objectId);
        if (!$link instanceof WikiPageLink) {
            return false;
        }

        // @igor-ignore - Repository call (Doctrine remove + flush of a request-scoped entity); no service state.
        $this->linkRepository->remove($link);
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

    private function resolverFor(string $type): ?WikiObjectLinkResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($type)) {
                return $resolver;
            }
        }

        return null;
    }
}
