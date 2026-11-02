<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Twig\MilkdownEditorTwig;

/**
 * The read-side assets of nowo-tech/milkdown-editor-bundle for wiki pages (1.13): its stylesheet (callouts, details,
 * marks, icons, math) and module script, whose `renderRichContent()` draws Mermaid diagrams and math in the page body
 * (wiki.js calls it on `[data-wiki-rich-content]`). Both tags carry the CSP nonce of the response.
 */
final readonly class WikiMilkdownRichContent
{
    /**
     * @param MilkdownEditorTwig $twig the Milkdown Twig helpers
     */
    public function __construct(
        private MilkdownEditorTwig $twig,
    ) {
    }

    /**
     * The `<link>` and `<script type="module">` tags.
     */
    public function assets(): string
    {
        return $this->twig->assets();
    }
}
