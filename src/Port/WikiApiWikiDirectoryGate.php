<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\Internal\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;

use function array_values;
use function sprintf;

/**
 * Asks a framework-free {@see WikiApiWikiDirectoryInterface} (1.36.0). A wiki named with a space id gets that space,
 * loaded through the wiki's private manager and re-checked byte for byte. 2.0 (audit ZBL-v-2): a space entity the
 * directory put on its answer is never used as it is: its id is read again the same way, so the guards, the quota and
 * the plan decide on the wiki's own row. A wiki whose space is not stored (purged meanwhile) is answered as missing
 * (404, never provisioned again); a provision that names no stored space fails closed (`LogicException`: a wrong
 * directory must never make the API open another space).
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
        // 1.55.2: the named spaces in one query (it was one read per wiki), each re-checked byte for byte; a wiki whose
        // space is no longer stored is left out, like a missing one.
        $ids = [];
        foreach ($answered as $wiki) {
            if ($wiki->spaceId !== null) {
                $ids[$wiki->spaceId] = $wiki->spaceId;
            }
        }
        $found = $ids !== [] ? $this->spaces->findByIds(array_values($ids)) : [];
        $wikis = [];
        foreach ($answered as $wiki) {
            if ($wiki->spaceId === null) {
                $wikis[] = $wiki;

                continue;
            }
            $space = $found[$wiki->spaceId] ?? null;
            if ($space instanceof WikiSpace && $space->getId() === $wiki->spaceId) {
                $wikis[] = $wiki->withSpace($space);
            }
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

        return $wiki?->space?->getId() === $space->getId() ? $wiki : null;
    }

    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiSpace
    {
        return $this->space($this->directory->provision($principal, $wiki));
    }

    /**
     * The wiki with the private manager's space of its id (an entity on the answer is never kept as it is); null when
     * that space is not stored, a wiki nobody opened (no id) as it is.
     */
    private function hydrate(WikiApiWiki $wiki): ?WikiApiWiki
    {
        if ($wiki->spaceId === null) {
            return $wiki;
        }
        $space = $this->stored($wiki->spaceId);

        return $space instanceof WikiSpace ? $wiki->withSpace($space) : null;
    }

    private function space(string $id): WikiSpace
    {
        return $this->stored($id) ?? throw new LogicException(sprintf('nowo_wiki.api.hooks.directory named the space "%s", which does not exist.', $id));
    }

    private function stored(string $id): ?WikiSpace
    {
        $space = $this->spaces->findById($id);

        return $space instanceof WikiSpace && $space->getId() === $id ? $space : null;
    }
}
