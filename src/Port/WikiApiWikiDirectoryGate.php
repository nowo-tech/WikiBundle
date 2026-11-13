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
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;

use function sprintf;

/**
 * Asks a framework-free {@see WikiApiWikiDirectoryInterface} (1.32.0). A wiki named with a space id gets that space,
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
        $wikis = [];
        foreach ($this->directory->list($principal, $filter) as $wiki) {
            $wikis[] = $this->hydrate($wiki);
        }

        return $wikis;
    }

    public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki
    {
        $wiki = $this->directory->find($principal, $slug);

        return $wiki === null ? null : $this->hydrate($wiki);
    }

    public function ofSpace(WikiApiPrincipal $principal, WikiSpace $space): ?WikiApiWiki
    {
        $wiki = $this->directory->ofSpace($principal, WikiSnapshots::space($space));

        return $wiki === null ? null : $this->hydrate($wiki);
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
