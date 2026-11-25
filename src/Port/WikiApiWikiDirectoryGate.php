<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiSpaceBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;

use function array_values;
use function sprintf;

/**
 * Asks a framework-free {@see WikiApiWikiDirectoryInterface} (1.36.0). A wiki named with a space id gets that space,
 * loaded and re-checked byte for byte; an id that names no stored space fails closed (`LogicException`: a wrong
 * directory must never make the API provision a second space or open another one).
 *
 * @internal
 */
final readonly class WikiApiWikiDirectoryGate implements WikiApiDirectoryInterface
{
    public function __construct(
        private WikiApiWikiDirectoryInterface $directory,
        private WikiSpaceRepositoryInterface $spaces,
    ) {
    }

    public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter): array
    {
        $answered = $this->directory->list($principal, $filter);
        // 1.55.2: the named spaces in one query when the repository can (it was one read per wiki); each is still
        // re-checked byte for byte and a missing one fails closed.
        $ids = [];
        foreach ($answered as $wiki) {
            if (!$wiki->space instanceof WikiSpace && $wiki->spaceId !== null) {
                $ids[$wiki->spaceId] = $wiki->spaceId;
            }
        }
        $found = $this->spaces instanceof WikiSpaceBatchFinderInterface && $ids !== [] ? $this->spaces->findByIds(array_values($ids)) : [];
        $wikis = [];
        foreach ($answered as $wiki) {
            $wikis[] = $wiki->spaceId !== null && isset($found[$wiki->spaceId]) && !$wiki->space instanceof WikiSpace ? $wiki->withSpace($found[$wiki->spaceId]) : $this->hydrate($wiki);
        }

        return $wikis;
    }

    public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki
    {
        $wiki = $this->directory->find($principal, $slug);

        return $wiki instanceof WikiApiWiki ? $this->hydrate($wiki) : null;
    }

    public function ofSpace(WikiApiPrincipal $principal, WikiSpace $space): ?WikiApiWiki
    {
        $wiki = $this->directory->ofSpace($principal, WikiSnapshots::space($space));
        if (!$wiki instanceof WikiApiWiki) {
            return null;
        }
        // Audit OX-I1: kept only when it names exactly the asked space (a twin's answer is re-checked).
        $wiki = $this->hydrate($wiki);

        return $wiki->space?->getId() === $space->getId() ? $wiki : null;
    }

    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiSpace
    {
        return $this->space($this->directory->provision($principal, $wiki));
    }

    private function hydrate(WikiApiWiki $wiki): WikiApiWiki
    {
        if ($wiki->space instanceof WikiSpace || $wiki->spaceId === null) {
            return $wiki;
        }

        return $wiki->withSpace($this->space($wiki->spaceId));
    }

    private function space(string $id): WikiSpace
    {
        $space = $this->spaces->findById($id);
        if (!$space instanceof WikiSpace || $space->getId() !== $id) {
            throw new LogicException(sprintf('nowo_wiki.api.hooks.directory named the space "%s", which does not exist.', $id));
        }

        return $space;
    }
}
