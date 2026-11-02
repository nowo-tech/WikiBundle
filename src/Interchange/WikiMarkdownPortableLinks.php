<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use function array_slice;
use function count;
use function explode;
use function implode;
use function min;
use function preg_replace_callback;
use function rawurlencode;
use function str_repeat;
use function str_replace;
use function strtolower;

/**
 * Turns the `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` page links of an exported Markdown page into ordinary
 * Markdown links to the exported files (1.13, Milkdown dialect exports), so Outline, Notion or any Markdown viewer
 * shows `[Title](other-page.md#anchor)` instead of the raw token. Links to pages outside the export stay tokens (no
 * title of a page the exporter did not include is ever written); code spans and fenced code are left untouched.
 */
final class WikiMarkdownPortableLinks
{
    /**
     * One pass over the document (CV-L3: no placeholder masking): fenced code (groups 1-2), a code span (group 3) or a
     * page link (groups 4-5). Code is returned unchanged, so a link inside code is never rewritten.
     */
    private const TOKENS = '/^( {0,3})(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\2[`~]*[ \t]*$|\z)|(`+)(?!`).+?(?<!`)\3(?!`)|\[\[wiki:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})(?:#([a-z0-9][a-z0-9-]{0,80}))?\]\]/ms';

    /**
     * Rewrites the page links of the exported pages.
     *
     * @param string $markdown Markdown of one exported page
     * @param string $fromDirectory directory of that page's file
     * @param array<string, array{path: string, title: string}> $exported page id => file path and title of each
     *                                                                    exported page
     */
    public static function rewrite(string $markdown, string $fromDirectory, array $exported): string
    {
        if ($exported === [] || !str_contains($markdown, '[[wiki:')) {
            return $markdown;
        }

        return preg_replace_callback(self::TOKENS, static function (array $match) use ($fromDirectory, $exported): string {
            $target = isset($match[4]) ? $exported[strtolower($match[4])] ?? null : null;
            if ($target === null) {
                return $match[0];
            }

            $anchor = isset($match[5]) ? '#' . $match[5] : '';

            return '[' . str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $target['title']) . '](' . self::relative($fromDirectory, $target['path']) . $anchor . ')';
        }, $markdown) ?? $markdown;
    }

    /**
     * Relative, URL-encoded path from a directory to a file (both under the export root, `/` or `\` separated).
     */
    public static function relative(string $fromDirectory, string $toFile): string
    {
        $from   = explode('/', str_replace('\\', '/', $fromDirectory));
        $to     = explode('/', str_replace('\\', '/', $toFile));
        $common = 0;
        $max    = min(count($from), count($to) - 1);
        while ($common < $max && $from[$common] === $to[$common]) {
            ++$common;
        }

        $segments = [];
        foreach (array_slice($to, $common) as $segment) {
            $segments[] = rawurlencode($segment);
        }

        return str_repeat('../', count($from) - $common) . implode('/', $segments);
    }
}
