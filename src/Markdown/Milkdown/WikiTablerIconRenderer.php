<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\DialectRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Node\TablerIcon;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;

/**
 * Renders `:tabler-<name>:` for wiki pages (1.13). The wiki sanitizer never lets SVG through, so an allowlisted icon
 * becomes a placeholder `<span class="nowo-md-icon" data-icon="<name>" role="img" aria-label="<name>"></span>` that
 * {@see WikiMilkdownIconHydrator} turns into the sprite `<svg><use>` after sanitizing; an unknown icon stays text, as
 * in the Milkdown renderer.
 */
final readonly class WikiTablerIconRenderer implements NodeRendererInterface
{
    /**
     * @param IconResolverInterface $icons icon allowlist and sprite of nowo-tech/milkdown-editor-bundle
     */
    public function __construct(
        private IconResolverInterface $icons,
    ) {
    }

    /**
     * @throws InvalidArgumentException for a node that is not a Tabler icon
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof TablerIcon) {
            throw new InvalidArgumentException('Unsupported node ' . $node::class);
        }

        $name   = DialectRenderer::e($node->name);
        $sprite = $this->icons->spriteUrl($node->name);
        if ($sprite === null || DialectRenderer::safeUrl($sprite) === '') {
            return ':tabler-' . $name . ':';
        }

        return '<span class="nowo-md-icon" data-icon="' . $name . '" role="img" aria-label="' . $name . '"></span>';
    }
}
