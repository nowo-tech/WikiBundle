<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\ElementInterface;

use function preg_match;
use function strtolower;
use function trim;

/**
 * Wraps a centred or right-aligned block in the Milkdown dialect alignment container `::: {align=center}` … `:::`
 * (1.13). Left (the default) and justified text are written as plain blocks.
 */
trait WikiAlignmentTrait
{
    private function align(ElementInterface $element, string $markdown): string
    {
        if (trim($markdown) === '' || preg_match('/text-align\s*:\s*(center|right)\b/i', $element->getAttribute('style'), $match) !== 1) {
            return $markdown;
        }

        return '::: {align=' . strtolower($match[1]) . "}\n" . trim($markdown) . "\n:::\n\n";
    }
}
