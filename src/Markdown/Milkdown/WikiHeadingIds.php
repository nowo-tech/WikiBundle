<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use Nowo\MilkdownEditorBundle\Markdown\HeadingIds;

use function count;

/**
 * Heading ids of wiki pages (1.16.2): `nowo-h-<slug>` with the editor's slugs ({@see HeadingIds::slug()}: lower-case,
 * runs outside letters, marks and digits become `-`, at most 80 characters, `section` when empty) and the same
 * duplicate suffixes (`-2`, `-3`… in document order, skipping a suffix an earlier heading already took literally).
 *
 * 1.40 (Milkdown 1.18): a heading's explicit id, the `{#id}` suffix that Milkdown's `HeadingIdSuffixParser` keeps in
 * the heading's data ({@see HeadingIds::explicit()}), wins: every valid explicit id is reserved first (the first
 * occurrence wins), then each other heading takes its slug or the lowest free `-N` ({@see HeadingIds::resolve()},
 * linear, as the 1.16.2 EP-L1 fix). At most {@see self::MAX_IDS} headings per document get an id (later ones get none).
 * The server never rewrites ids in the stored Markdown: duplicates and invalid ids are only resolved here, at render
 * time. Stateless (worker-safe).
 */
final readonly class WikiHeadingIds
{
    /** Headings per document that get an id. */
    public const MAX_IDS = HeadingIds::MAX_IDS;

    /**
     * Unique slugs of headings in order (headings without an explicit id).
     *
     * @param list<string> $texts heading texts in document order
     *
     * @return list<string>
     */
    public static function slugs(array $texts): array
    {
        return HeadingIds::slugs($texts);
    }

    /**
     * Sets the `id` of the first {@see self::MAX_IDS} headings of the parsed document.
     *
     * @param DocumentParsedEvent $event the parsed document
     */
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $headings = [];
        $input    = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Heading) {
                $headings[] = $node;
                $input[]    = ['text' => HeadingIds::text($node), 'explicit' => HeadingIds::explicit($node)];
                if (count($headings) === self::MAX_IDS) {
                    break;
                }
            }
        }
        foreach (HeadingIds::resolve($input) as $i => $id) {
            $headings[$i]->data->set('attributes/id', HeadingIds::PREFIX . $id);
        }
    }
}
