<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218): one viewer render in progress — the space and page it renders, the reach it may not go beyond, and
 * the page links the resolver resolved for it (URL → page id). Lives only for the render ({@see WikiRenderContext}).
 */
final class WikiRenderFrame
{
    /** @var array<string, string> URL => page id */
    private array $pageLinks = [];

    public function __construct(
        public readonly WikiSpace $space,
        public readonly ?WikiPage $page = null,
        public readonly ?WikiRenderReach $reach = null,
    ) {
    }

    /** Whether the render may show something of the space (always, without a reach). */
    public function reaches(WikiSpace $space): bool
    {
        return !$this->reach instanceof WikiRenderReach || $this->reach->reachesSpace($space);
    }

    /** Records that a page link of this render was resolved to the URL. */
    public function recordPageLink(string $url, string $pageId): void
    {
        if ($url !== '') {
            // @igor-ignore - One frame per render, made and dropped by WikiRenderContext; never a shared service.
            $this->pageLinks[$url] = $pageId;
        }
    }

    /** The page a resolved link URL of this render points to, or null. */
    public function pageIdOfLink(string $url): ?string
    {
        return $this->pageLinks[$url] ?? null;
    }
}
