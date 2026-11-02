<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\DialectRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;

use function preg_replace_callback;

/**
 * Turns the icon placeholders of {@see WikiTablerIconRenderer} into the Milkdown sprite markup **after** the wiki
 * sanitizer ran (1.13): `<svg class="nowo-md-icon" role="img" aria-label="<name>"><use href="<sprite>#tabler-<name>">`.
 *
 * Only names the icon allowlist resolves are drawn, with the sprite URL of the configuration (relative or http(s));
 * any other placeholder is removed. The input is sanitized HTML, so the placeholder is in its canonical form.
 */
final readonly class WikiMilkdownIconHydrator
{
    private const PLACEHOLDER = '#<span\b(?=[^>]*\bclass="nowo-md-icon")[^>]*\bdata-icon="([a-z0-9]+(?:-[a-z0-9]+)*)"[^>]*>\s*</span>#';

    /**
     * @param IconResolverInterface $icons icon allowlist and sprite of nowo-tech/milkdown-editor-bundle
     */
    public function __construct(
        private IconResolverInterface $icons,
    ) {
    }

    /**
     * Replaces every icon placeholder of sanitized HTML.
     *
     * @param string $html sanitized page HTML
     *
     * @return string HTML with the allowlisted icons drawn
     */
    public function hydrate(string $html): string
    {
        if (!str_contains($html, 'nowo-md-icon')) {
            return $html;
        }

        return preg_replace_callback(self::PLACEHOLDER, function (array $match): string {
            $sprite = $this->icons->spriteUrl($match[1]);
            $sprite = $sprite === null ? '' : DialectRenderer::safeUrl($sprite);
            if ($sprite === '') {
                return '';
            }

            $name = DialectRenderer::e($match[1]);

            return '<svg class="nowo-md-icon" role="img" aria-label="' . $name . '"><use href="' . DialectRenderer::e($sprite . '#tabler-' . $match[1]) . '"></use></svg>';
        }, $html) ?? $html;
    }
}
