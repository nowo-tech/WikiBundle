<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\SectionResolverInterface;
use Nowo\WikiBundle\Markdown\WikiHeadingOutline;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTitleFinderInterface;

use function array_keys;
use function array_values;

/**
 * 1.40 (owner decision O3): the text of the sections that `[[wiki:<uuid>#<id>]]` links point at, so a page link reads
 * `Title › Section text` (Milkdown 1.18 `SectionResolverInterface`; a missing section reads `Title`, never the raw id).
 *
 * Milkdown asks only about pages that {@see WikiMilkdownPageResolver} resolved for this viewer in this render; inside a
 * {@see WikiRenderFrame} a page the frame did not record as resolved is left out as well. The outline is read from the
 * current revision's **viewer-neutral** `content_html` cache with one query ({@see WikiPageTitleFinderInterface}), top-level
 * headings only (never a heading inside a callout, details or host container), text only ({@see WikiHeadingOutline}: no
 * titles of other pages, no mentions). Nothing is kept between calls (worker mode).
 */
final readonly class WikiMilkdownSectionResolver implements SectionResolverInterface
{
    /**
     * @param WikiPageRepositoryInterface $pages page lookup (one query when it implements {@see WikiPageTitleFinderInterface})
     * @param WikiRenderContext|null $renderContext the render in progress
     */
    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private ?WikiRenderContext $renderContext = null,
    ) {
    }

    public function sectionTitles(array $anchors): array
    {
        $frame = $this->renderContext?->current();
        $ids   = [];
        foreach (array_keys($anchors) as $pageId) {
            if (!$frame instanceof WikiRenderFrame || $frame->hasResolvedPage((string) $pageId)) {
                $ids[] = (string) $pageId;
            }
        }
        if ($ids === []) {
            return [];
        }

        $titles = [];
        foreach ($this->html($ids) as $pageId => $html) {
            $wanted = [];
            foreach ($anchors[$pageId] ?? [] as $id) {
                $wanted[$id] = true;
            }
            foreach (WikiHeadingOutline::fromHtml($html, true) as $section) {
                if (isset($wanted[$section['id']])) {
                    $titles[$pageId][$section['id']] = $section['text'];
                }
            }
        }

        return $titles;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function html(array $ids): array
    {
        if ($this->pages instanceof WikiPageTitleFinderInterface) {
            return $this->pages->currentHtml(array_values($ids));
        }

        $html = [];
        foreach ($ids as $id) {
            $revision = $this->pages->findById($id)?->getCurrentRevision();
            if ($revision !== null) {
                $html[$id] = $revision->getContentHtml();
            }
        }

        return $html;
    }
}
