<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\RenderContextProviderInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;

/**
 * 1.25: the wiki's render context as Milkdown's host file resolvers get it (Milkdown 1.13
 * `ContextualHostFileResolverInterface`): the {@see WikiRenderFrame} of the render in progress — space, page and the
 * API's reach — or null outside a render. Holds no state of its own.
 */
final readonly class WikiMilkdownRenderContext implements RenderContextProviderInterface
{
    public function __construct(
        private WikiRenderContext $context,
    ) {
    }

    public function renderContext(): ?WikiRenderFrame
    {
        return $this->context->current();
    }
}
