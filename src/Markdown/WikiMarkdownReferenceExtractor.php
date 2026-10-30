<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Link\WikiPageLinkService;

use function array_keys;
use function array_values;
use function preg_match_all;
use function preg_replace;
use function strtolower;

use const PREG_SET_ORDER;

/**
 * Finds the page references of a Markdown revision (1.11, spec dialect `[[wiki:<uuid>]]` and
 * `[[wiki:<uuid>#anchor]]`), outside code spans and fenced / indented code.
 *
 * The bundle does not index them itself: its `<prefix>_page_links` table holds host objects linked by hand
 * ({@see WikiPageLinkService}), and a save must never unlink those. A host that keeps a backlink
 * index listens to {@see WikiEvents::PAGE_SAVED} and calls {@see self::pageReferences()} on
 * `$event->revision->getContentMarkdown()`.
 */
final readonly class WikiMarkdownReferenceExtractor
{
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
}
