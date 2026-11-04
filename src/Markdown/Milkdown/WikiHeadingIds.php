<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Nowo\MilkdownEditorBundle\Markdown\HeadingIds;

use function count;

/**
 * Heading ids of wiki pages (1.16.2): `nowo-h-<slug>` with the editor's slugs ({@see HeadingIds::slug()}: lower-case,
 * runs outside letters, marks and digits become `-`, at most 80 characters, `section` when empty) and the same
 * duplicate suffixes (`-2`, `-3`… in document order, skipping a suffix an earlier heading already took literally).
 *
 * Unlike {@see HeadingIds::slugs()}, which retries every suffix from `-2` for each duplicate (N identical headings
 * cost ~N²/2, audit EP-L1), the next free suffix is kept per base, so a document is linear; the output is the same.
 * At most {@see self::MAX_IDS} headings per document get an id (later ones get none). Stateless (worker-safe).
 */
final readonly class WikiHeadingIds
{
    /** Headings per document that get an id. */
    public const MAX_IDS = 1000;

    /**
     * Unique slugs of headings in order (the same result as {@see HeadingIds::slugs()}, in linear time).
     *
     * @param list<string> $texts heading texts in document order
     *
     * @return list<string>
     */
    public static function slugs(array $texts): array
    {
        $used = [];
        $next = [];
        $out  = [];
        foreach ($texts as $text) {
            $base      = HeadingIds::slug($text);
            $candidate = $base;
            while (isset($used[$candidate])) {
                $next[$base] ??= 2;
                $candidate = $base . '-' . $next[$base]++;
            }
            $used[$candidate] = true;
            $out[]            = $candidate;
        }

        return $out;
    }

    /**
     * Sets the `id` of the first {@see self::MAX_IDS} headings of the parsed document.
     *
     * @param DocumentParsedEvent $event the parsed document
     */
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $headings = [];
        $texts    = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Heading) {
                $headings[] = $node;
                $texts[]    = $this->text($node);
                if (count($headings) === self::MAX_IDS) {
                    break;
                }
            }
        }
        foreach (self::slugs($texts) as $i => $slug) {
            $headings[$i]->data->set('attributes/id', HeadingIds::PREFIX . $slug);
        }
    }

    /**
     * The text of a heading as the editor counts it: text and inline code only.
     */
    private function text(Node $heading): string
    {
        $text = '';
        foreach ($heading->iterator() as $node) {
            if ($node instanceof Text || $node instanceof Code) {
                $text .= $node->getLiteral();
            }
        }

        return $text;
    }
}
