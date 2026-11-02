<?php

declare(strict_types=1);

namespace App\Twig;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

use function in_array;

/**
 * Demo-only "Linked from" panel: the pages of the space whose current Markdown links to the page, found with the
 * bundle's reference extractor (the same parser as the renderer). A real host keeps a link index updated on
 * nowo_wiki.page.saved (see docs/USAGE.md#markdown-pages-111) and filters it by the access checker; the demo reads the
 * small seeded space on the fly (every demo page is readable by the demo user).
 */
final readonly class DemoBacklinksExtension
{
    public function __construct(
        private WikiSpaceRepositoryInterface $spaces,
        private WikiPageRepositoryInterface $pages,
        private WikiMarkdownReferenceExtractor $references,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    #[AsTwigFunction('demo_wiki_backlinks')]
    public function backlinks(string $spaceSlug, string $pageSlug): array
    {
        $space = $this->spaces->findFirstBySlug($spaceSlug);
        $page  = $space instanceof WikiSpace ? $this->pages->findBySlug($space, $pageSlug) : null;
        if (!$page instanceof WikiPage) {
            return [];
        }

        $links = [];
        foreach ($this->pages->findActiveBySpace($page->getSpace(), 200) as $source) {
            if ($source->getId() !== $page->getId() && in_array($page->getId(), $this->references->pageIds($source->getCurrentRevision()?->getContentMarkdown()), true)) {
                $links[] = [
                    'title' => $source->getTitle(),
                    'href'  => $this->urls->generate('nowo_wiki_page_view', ['spaceSlug' => $source->getSpace()->getSlug(), 'pageSlug' => $source->getSlug()]),
                ];
            }
        }

        return $links;
    }
}
