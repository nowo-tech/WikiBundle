<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Reference\Reference;
use Nowo\WikiBundle\Security\AttributeSanitizer\AnchorIdAttributeSanitizer;

use function count;
use function hash_final;
use function hash_init;
use function hash_update;
use function substr;

/**
 * Footnote ids the wiki sanitizer keeps (1.16.2). league/commonmark builds them from the user's label (`[^a"b]` gives
 * `id="nowo-fn-a&quot;b"`), which may hold quotes or any character, so every label is renamed to `<salt>-<n>`: `<n>`
 * numbers the distinct labels in document order and `<salt>` is 8 hex characters of a hash of the document's text, so
 * a page and the comments shown next to it never share an id while the same document always gets the same ids (stored
 * HTML stays stable). The ids become `nowo-fn-<salt>-<n>` and `nowo-fn-ref-<salt>-<n>[__<k>]`, the only footnote ids
 * {@see AnchorIdAttributeSanitizer} accepts.
 *
 * Runs after orphans were restored as text (30) and before numbering (20). Stateless (worker-safe).
 */
final readonly class WikiFootnoteIds
{
    /** Footnote id prefix (league/commonmark `footnote/footnote_id_prefix`). */
    public const FOOTNOTE_PREFIX = 'nowo-fn-';

    /** Footnote reference id prefix (league/commonmark `footnote/ref_id_prefix`). */
    public const REF_PREFIX = 'nowo-fn-ref-';

    /**
     * Renames the label of every footnote and footnote reference.
     *
     * @param DocumentParsedEvent $event the parsed document
     */
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $hash  = hash_init('sha256');
        $refs  = [];
        $notes = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof StringContainerInterface) {
                hash_update($hash, $node->getLiteral() . "\0");
            } elseif ($node instanceof FootnoteRef) {
                $refs[] = $node;
                hash_update($hash, '[^' . $node->getReference()->getLabel() . "]\0");
            } elseif ($node instanceof Footnote) {
                $notes[] = $node;
            }
        }
        $salt = substr(hash_final($hash), 0, 8);

        /** @var array<string, string> $labels */
        $labels = [];
        foreach ($refs as $ref) {
            $reference = $ref->getReference();
            $label     = $labels[$reference->getLabel()] ??= $salt . '-' . (count($labels) + 1);
            $ref->setReference(new Reference($label, '#' . self::FOOTNOTE_PREFIX . $label, $reference->getTitle()));
        }
        foreach ($notes as $note) {
            $label   = $labels[$note->getReference()->getLabel()] ??= $salt . '-' . (count($labels) + 1);
            $renamed = new Footnote(new Reference($label, $label, $label));
            foreach ($note->children() as $child) {
                $renamed->appendChild($child);
            }
            $note->replaceWith($renamed);
        }
    }
}
