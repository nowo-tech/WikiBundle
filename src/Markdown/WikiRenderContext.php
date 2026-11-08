<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Contracts\Service\ResetInterface;

use function array_pop;
use function end;

/**
 * 1.24 (spec 218): what the current viewer render is rendering. The bundle sets it around every per-viewer render (page
 * body, comments, the API's viewer view), so resolvers and directive renderers — the bundle's and the host's — get the
 * space and page explicitly instead of reading route attributes, and the API's reach is honoured everywhere.
 *
 * Request-scoped state, emptied after each render and by `kernel.reset` (FrankenPHP worker mode).
 */
final class WikiRenderContext implements ResetInterface
{
    /** @var list<WikiRenderFrame> */
    private array $frames = [];

    /** The innermost render in progress, or null outside any. */
    public function current(): ?WikiRenderFrame
    {
        $frame = end($this->frames);

        return $frame instanceof WikiRenderFrame ? $frame : null;
    }

    /**
     * Runs a render inside a frame. A nested render keeps the outer reach; given its own, it gets both (audit IF-I7), so a
     * nested render can only narrow.
     *
     * @template T
     *
     * @param callable(WikiRenderFrame): T $render
     *
     * @return T
     */
    public function within(WikiSpace $space, ?WikiPage $page, callable $render, ?WikiRenderReach $reach = null): mixed
    {
        $outer          = $this->current()?->reach;
        $frame          = new WikiRenderFrame($space, $page, $reach instanceof WikiRenderReach && $outer instanceof WikiRenderReach ? new WikiRenderReachIntersection($outer, $reach) : $reach ?? $outer);
        $this->frames[] = $frame;
        try {
            return $render($frame);
        } finally {
            array_pop($this->frames);
        }
    }

    public function reset(): void
    {
        $this->frames = [];
    }
}
