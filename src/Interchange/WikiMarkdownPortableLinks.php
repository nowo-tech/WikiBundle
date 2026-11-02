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
    private const CODE = '/^( {0,3})(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\2[`~]*[ \t]*$|\z)|(`+)(?!`).+?(?<!`)\3(?!`)/ms';

    private const PAGE_LINK = '/\[\[wiki:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})(?:#([a-z0-9][a-z0-9-]{0,80}))?\]\]/';

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

        $protected = [];
        $masked    = preg_replace_callback(self::CODE, static function (array $match) use (&$protected): string {
            $protected[] = $match[0];

            return "\x00" . (count($protected) - 1) . "\x00";
        }, $markdown) ?? $markdown;

        $linked = preg_replace_callback(self::PAGE_LINK, static function (array $match) use ($fromDirectory, $exported): string {
            $target = $exported[strtolower($match[1])] ?? null;
            if ($target === null) {
                return $match[0];
            }

            $anchor = isset($match[2]) ? '#' . $match[2] : '';

            return '[' . str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $target['title']) . '](' . self::relative($fromDirectory, $target['path']) . $anchor . ')';
        }, $masked) ?? $masked;

        return preg_replace_callback("/\x00(\\d+)\x00/", static fn (array $match): string => $protected[(int) $match[1]], $linked) ?? $linked;
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
