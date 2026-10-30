<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\ListItemConverter;
use League\HTMLToMarkdown\ElementInterface;

use function preg_replace;

/**
 * List items, with the Tiptap task items (`<li data-type="taskItem" data-checked="true">`) written as GFM task list
 * items (`- [x] …`) instead of plain ones (1.11).
 */
final class WikiTaskItemConverter extends ListItemConverter
{
    public function convert(ElementInterface $element): string
    {
        $markdown = parent::convert($element);
        if ($element->getAttribute('data-type') !== 'taskItem') {
            return $markdown;
        }

        $box = $element->getAttribute('data-checked') === 'true' ? '[x] ' : '[ ] ';

        return preg_replace('/^(\n?(?:[-*+]|\d+\.) )/', '${1}' . $box, $markdown, 1) ?? $markdown;
    }
}
