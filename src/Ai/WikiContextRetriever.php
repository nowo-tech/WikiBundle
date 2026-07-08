<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function count;
use function in_array;
use function preg_split;
use function sprintf;
use function strip_tags;
use function trim;

/**
 * Retrieves wiki page excerpts as LLM context via the existing search service.
 */
final readonly class WikiContextRetriever
{
    public function __construct(
        private WikiSearchService $searchService,
        private WikiSpaceAccessResolverInterface $spaceAccessResolver,
    ) {
    }

    /**
     * @return array{context: string, sources: list<array{page: WikiPage, space: WikiSpace, excerpt: string}>}
     */
    public function retrieve(
        UserInterface $user,
        string $question,
        ?WikiSpace $space,
        int $maxPages,
        int $maxChars,
    ): array {
        $question = trim($question);
        if ($question === '') {
            return ['context' => '', 'sources' => []];
        }

        $spaces = $space instanceof WikiSpace
            ? [$space]
            : $this->spaceAccessResolver->listSpacesForUser($user);

        $sources = $this->collectSources($spaces, $question, $space, $maxPages);
        $context = $this->buildContext($sources, $maxChars);

        return ['context' => $context, 'sources' => $sources];
    }

    /**
     * @param list<WikiSpace> $spaces
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    private function collectSources(array $spaces, string $question, ?WikiSpace $scopedSpace, int $maxPages): array
    {
        if ($spaces === []) {
            return [];
        }

        $queries = [$question];
        foreach ($this->extractTerms($question) as $term) {
            if (!in_array($term, $queries, true)) {
                $queries[] = $term;
            }
        }

        /** @var array<string, array{page: WikiPage, space: WikiSpace, excerpt: string}> $indexed */
        $indexed = [];

        foreach ($queries as $query) {
            $batch = $scopedSpace instanceof WikiSpace
                ? $this->searchService->search($scopedSpace, $query, $maxPages)
                : $this->searchService->searchAcrossSpaces($spaces, $query, $maxPages);

            foreach ($batch as $hit) {
                $indexed[$hit['page']->getId()] = $hit;
                if (count($indexed) >= $maxPages) {
                    break 2;
                }
            }
        }

        return array_values($indexed);
    }

    /**
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $sources
     */
    private function buildContext(array $sources, int $maxChars): string
    {
        if ($sources === []) {
            return '';
        }

        $chunks = [];
        $length = 0;
        $budget = max(500, $maxChars);

        foreach ($sources as $source) {
            $body = trim(strip_tags($source['page']->getCurrentRevision()?->getContentHtml() ?? ''));
            if ($body === '') {
                $body = $source['excerpt'];
            }

            $chunk = sprintf(
                "### %s (%s/%s)\n%s",
                $source['page']->getTitle(),
                $source['space']->getSlug(),
                $source['page']->getSlug(),
                mb_substr($body, 0, 2000),
            );

            if ($length + mb_strlen($chunk) > $budget) {
                break;
            }

            $chunks[] = $chunk;
            $length += mb_strlen($chunk);
        }

        return implode("\n\n", $chunks);
    }

    /**
     * @return list<string>
     */
    private function extractTerms(string $question): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower($question)) ?: [];
        $terms = [];

        foreach ($parts as $part) {
            $part = trim($part, ".,;:!?\"'()[]");
            if (mb_strlen($part) >= 4) {
                $terms[] = $part;
            }
        }

        return $terms;
    }
}
