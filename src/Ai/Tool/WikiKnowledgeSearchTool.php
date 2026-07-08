<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai\Tool;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Symfony AI tool: search wiki pages the current user can access.
 */
#[AsTool(
    name: 'wiki_knowledge_search',
    description: 'Search wiki documentation pages by keyword. Returns JSON with space slug, page slug, title, and excerpt.',
)]
final readonly class WikiKnowledgeSearchTool
{
    public function __construct(
        private Security $security,
        private WikiSearchService $searchService,
        private WikiSpaceAccessResolverInterface $spaceAccessResolver,
    ) {
    }

    /**
     * @return string JSON-encoded hits
     */
    public function __invoke(string $query, ?string $space_slug = null, int $limit = 8): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return json_encode(['error' => 'Authentication required.'], JSON_THROW_ON_ERROR);
        }

        $query = trim($query);
        if ($query === '') {
            return json_encode(['results' => []], JSON_THROW_ON_ERROR);
        }

        $limit  = max(1, min($limit, 25));
        $space  = $this->resolveSpace($user, $space_slug);
        $spaces = $this->spaceAccessResolver->listSpacesForUser($user);

        $hits = $space instanceof WikiSpace
            ? $this->searchService->search($space, $query, $limit)
            : $this->searchService->searchAcrossSpaces($spaces, $query, $limit);

        $results = [];
        foreach ($hits as $hit) {
            $results[] = [
                'space'   => $hit['space']->getSlug(),
                'page'    => $hit['page']->getSlug(),
                'title'   => $hit['page']->getTitle(),
                'excerpt' => $hit['excerpt'],
            ];
        }

        return json_encode(['results' => $results], JSON_THROW_ON_ERROR);
    }

    private function resolveSpace(UserInterface $user, ?string $spaceSlug): ?WikiSpace
    {
        if ($spaceSlug === null || $spaceSlug === '') {
            return null;
        }

        foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $space) {
            if ($space->getSlug() === $spaceSlug) {
                return $space;
            }
        }

        return null;
    }
}
