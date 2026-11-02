<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Link\WikiPageLinkService;

use function array_keys;
use function array_unique;
use function array_values;
use function preg_match_all;
use function preg_replace;
use function strtolower;

use const PREG_SET_ORDER;

/**
 * Finds the page references of a Markdown revision (1.11, spec dialect `[[wiki:<uuid>]]` and
 * `[[wiki:<uuid>#anchor]]`), outside code spans and fenced / indented code.
 *
 * 1.13: when the Markdown renderer is a {@see WikiMarkdownReferenceParserInterface} (`editor.type: milkdown`) the
 * references come from the renderer's own parser, so the index gets exactly the links and mentions a reader sees;
 * {@see self::mentions()} lists the `@handle` mentions.
 *
 * The bundle does not index them itself: its `<prefix>_page_links` table holds host objects linked by hand
 * ({@see WikiPageLinkService}), and a save must never unlink those. A host that keeps a backlink
 * index listens to {@see WikiEvents::PAGE_SAVED} and calls {@see self::pageReferences()} on
 * `$event->revision->getContentMarkdown()`.
 */
final readonly class WikiMarkdownReferenceExtractor
{
    /** A mention after the start of text, whitespace or `(` (never inside an e-mail address), outside code. */
    private const MENTION = '/(?<![^\s(])@([a-zA-Z0-9_](?:[a-zA-Z0-9_.-]{0,38}[a-zA-Z0-9_])?)/u';

    private ?WikiMarkdownReferenceParserInterface $parser;

    /**
     * @param WikiMarkdownRendererInterface|null $renderer the bundle renderer; used as parser when it is a
     *                                                     {@see WikiMarkdownReferenceParserInterface} (1.13)
     */
    public function __construct(?WikiMarkdownRendererInterface $renderer = null)
    {
        $this->parser = $renderer instanceof WikiMarkdownReferenceParserInterface ? $renderer : null;
    }

    private const PAGE_REFERENCE = '/\[\[wiki:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})(?:#([A-Za-z0-9_-]{1,128}))?\]\]/';

    private const CODE = [
        '/^( {0,3})(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\2[`~]*[ \t]*$|\z)/ms',
        '/^(?: {4}|\t)[^\n]*$/m',
        '/(`+)(?!`).+?(?<!`)\1(?!`)/s',
    ];

    /**
     * Returns the distinct page references, in order of first appearance.
     *
     * @param string|null $markdown Markdown source (null for an HTML revision)
     *
     * @return list<array{id: string, anchor: string|null}> page id (lower case) and optional heading anchor
     */
    public function pageReferences(?string $markdown): array
    {
        if ($markdown === null || $markdown === '') {
            return [];
        }

        if ($this->parser instanceof WikiMarkdownReferenceParserInterface) {
            return $this->parser->references($markdown)['pages'];
        }

        $text = preg_replace(self::CODE, '', $markdown) ?? '';
        if (preg_match_all(self::PAGE_REFERENCE, $text, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $references = [];
        foreach ($matches as $match) {
            $id     = strtolower($match[1]);
            $anchor = isset($match[2]) && $match[2] !== '' ? $match[2] : null;

            $references[$id . '#' . ($anchor ?? '')] = ['id' => $id, 'anchor' => $anchor];
        }

        return array_values($references);
    }

    /**
     * Returns the distinct page ids referenced by the Markdown.
     *
     * @param string|null $markdown Markdown source (null for an HTML revision)
     *
     * @return list<string> page ids (lower case)
     */
    public function pageIds(?string $markdown): array
    {
        $ids = [];
        foreach ($this->pageReferences($markdown) as $reference) {
            $ids[$reference['id']] = true;
        }

        return array_keys($ids);
    }

    /**
     * Returns the distinct `@handle` mentions (without `@`), in order of first appearance (1.13).
     *
     * @param string|null $markdown Markdown source (null for an HTML revision)
     *
     * @return list<string> handles
     */
    public function mentions(?string $markdown): array
    {
        if ($markdown === null || $markdown === '') {
            return [];
        }

        if ($this->parser instanceof WikiMarkdownReferenceParserInterface) {
            return $this->parser->references($markdown)['mentions'];
        }

        $text = preg_replace(self::CODE, '', $markdown) ?? '';
        preg_match_all(self::MENTION, $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
