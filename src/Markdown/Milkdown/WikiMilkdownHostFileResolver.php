<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\ContextualHostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\HostFileResolverInterface;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;

/**
 * 1.25: the host's Milkdown file resolver (`HostFileResolverInterface`: `scheme:<id>` references and, with Milkdown's
 * `host_url_patterns`, plain file URLs) as the wiki's per-viewer render calls it.
 *
 * A {@see ContextualHostFileResolverInterface} gets the {@see WikiRenderFrame} of the render (space, page, reach) and
 * must refuse a file whose space the frame's reach does not reach. A resolver that cannot see the frame cannot honour a
 * reach, so under one (the REST API's *where*) it resolves nothing: those files read *Content not visible*. Without a
 * reach (the UI) it is called as before.
 */
final readonly class WikiMilkdownHostFileResolver implements ContextualHostFileResolverInterface
{
    public function __construct(
        private HostFileResolverInterface $inner,
    ) {
    }

    public function resolveFiles(array $references): array
    {
        return $this->inner->resolveFiles($references);
    }

    public function resolveFilesInContext(array $references, ?object $context): array
    {
        if ($this->inner instanceof ContextualHostFileResolverInterface) {
            return $this->inner->resolveFilesInContext($references, $context);
        }
        if ($context instanceof WikiRenderFrame && $context->reach instanceof WikiRenderReach) {
            return [];
        }

        return $this->inner->resolveFiles($references);
    }
}
