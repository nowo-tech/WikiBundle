<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

use function ltrim;
use function str_starts_with;

/**
 * Turns GFM task list items into the markup the Tiptap editor and the wiki sanitizer use (1.11): the list gets
 * `data-type="taskList"`, each item `data-type="taskItem"` and `data-checked="true|false"`, and the `<input>` checkbox
 * league/commonmark would render (stripped by the sanitizer) is removed.
 */
final class WikiTaskListNormalizer
{
    /**
     * Rewrites every task list marker of the parsed document.
     *
     * @param DocumentParsedEvent $event league/commonmark event fired after parsing
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $markers = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof TaskListItemMarker) {
                $markers[] = $node;
            }
        }

        foreach ($markers as $marker) {
            $item = $marker->parent();
            while ($item instanceof Node && !$item instanceof ListItem) {
                $item = $item->parent();
            }

            if ($item instanceof ListItem) {
                $item->data->set('attributes/data-type', 'taskItem');
                $item->data->set('attributes/data-checked', $marker->isChecked() ? 'true' : 'false');
                $list = $item->parent();
                if ($list instanceof ListBlock) {
                    $list->data->set('attributes/data-type', 'taskList');
                }
            }

            $next = $marker->next();
            if ($next instanceof Text && str_starts_with($next->getLiteral(), ' ')) {
                $next->setLiteral(ltrim($next->getLiteral(), ' '));
            }

            $marker->detach();
        }
    }
}
