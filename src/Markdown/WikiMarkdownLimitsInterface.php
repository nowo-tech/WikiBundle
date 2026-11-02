<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

/**
 * A Markdown renderer with an input bound (1.13): documents over it are shown as escaped plain text, unparsed (the
 * Milkdown bridge applies `max_input_length` and `max_inline_markers`). The conversion report flags them.
 */
interface WikiMarkdownLimitsInterface
{
    /**
     * Whether the document is over the renderer's limits (rendered as plain text).
     *
     * @param string $markdown Markdown source
     */
    public function exceedsLimits(string $markdown): bool;
}
