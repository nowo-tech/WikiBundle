<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\PageFile;

use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;

use function count;
use function html_entity_decode;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strlen;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;

/**
 * Lists the files a page's Markdown refers to (1.20, `page_files`): Milkdown 1.8 file cards
 * `::file[name]{href="…" size="…" type="…"}` and images `![alt](url)`, outside code, in order of first appearance,
 * without duplicates. Only relative and http(s) URLs are kept (no `javascript:`, `data:` or protocol-relative ones);
 * names lose control and invisible format characters (Unicode Cc / Cf) and are cut at 200 characters. HTML revisions
 * have no Markdown: the list is empty.
 *
 * Best effort, not a CommonMark parser (audit GR-I9): cards are found on their own line, also inside block quotes and
 * list items (`> `, `- `, `1. ` prefixes); attributes are read like Milkdown's `DirectiveAttributes` (a repeated key
 * keeps its **last** value, as the renderer does); code blocks, code spans and HTML comments are skipped, and escaped
 * `\![…](…)` is not an image. Not listed: reference-style images `![alt][ref]`, images in raw HTML (`<img>`), and
 * cards a host renders differently. Treat the result as editor-supplied, untrusted data.
 */
final readonly class WikiPageFileExtractor
{
    /** Default maximum of files listed for one page. */
    public const DEFAULT_MAX_FILES = 200;

    /** Longest name kept (characters). */
    private const MAX_NAME = 200;

    /** Fenced code, indented code and code spans (as {@see WikiMarkdownReferenceExtractor}). */
    private const CODE = [
        '/^( {0,3})(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\2[`~]*[ \t]*$|\z)/ms',
        '/^(?: {4}|\t)[^\n]*$/m',
        '/(`+)(?!`).+?(?<!`)\1(?!`)/s',
    ];

    /**
     * A file card on its own line, after optional block quote / list item markers (possessive: no backtracking on
     * long lines).
     */
    private const CARD = '/^(?:[ \t]{0,3}+(?:>[ \t]?+|[-*+][ \t]++|\d{1,9}+[.)][ \t]++))*+[ \t]*+::file(?:\[([^\]\n]*+)\])?+[ \t]*+\{([^}\n]*+)\}[ \t]*+$/m';

    /** An image: `![alt](url)` or `![alt](url "title")`, the URL without spaces or `<…>` wrapped; not after `\`. */
    private const IMAGE = '/(?<!\\\\)!\[([^\]\n]{0,500})\]\(\s*(?:<([^>\n]{1,2048})>|([^\s()<>]{1,2048}))(?:\s+(?:"[^"\n]*"|\'[^\'\n]*\'))?\s*\)/';

    /** HTML comments (skipped like code). */
    private const COMMENT = '/<!--.*?-->/s';

    /** One attribute token, anchored at the offset (Milkdown's `DirectiveAttributes::TOKEN`). */
    private const TOKEN = '/\G\s*(?:([#.])([^\s"\'=<>`#.}]+)|([a-z][a-z0-9_-]{0,40})(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`}]+)))?)/';

    /**
     * @param int $maxFiles most files listed per page
     */
    public function __construct(private int $maxFiles = self::DEFAULT_MAX_FILES)
    {
    }

    /**
     * Returns the files of the Markdown.
     *
     * @param string|null $markdown Markdown source (null for an HTML revision)
     *
     * @return list<WikiPageFile> files in order of first appearance
     */
    public function files(?string $markdown): array
    {
        if ($markdown === null || trim($markdown) === '') {
            return [];
        }
        $text  = (string) preg_replace([...self::CODE, self::COMMENT], '', str_replace(["\r\n", "\r"], "\n", $markdown));
        $found = [];

        if (preg_match_all(self::CARD, $text, $cards, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($cards as $card) {
                $attributes = $this->attributes($card[2][0]);
                $url        = self::safeUrl($attributes['href'] ?? '');
                if ($url === '') {
                    continue;
                }
                $size               = $attributes['size'] ?? '';
                $type               = $attributes['type'] ?? '';
                $found[$card[0][1]] = new WikiPageFile(
                    $url,
                    self::name($card[1][0] ?? ''),
                    WikiPageFile::KIND_FILE,
                    preg_match('/^\d{1,15}$/', $size) === 1 ? (int) $size : null,
                    preg_match('#^[a-z0-9][a-z0-9!\#$&^_.+-]{0,63}/[a-z0-9][a-z0-9!\#$&^_.+-]{0,126}$#i', $type) === 1 ? $type : null,
                );
            }
        }
        if (preg_match_all(self::IMAGE, $text, $images, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($images as $image) {
                $url = self::safeUrl(($image[2][0] ?? '') !== '' ? $image[2][0] : ($image[3][0] ?? ''));
                if ($url !== '') {
                    $found[$image[0][1]] = new WikiPageFile($url, self::name($image[1][0]), WikiPageFile::KIND_IMAGE);
                }
            }
        }
        ksort($found);

        $files = [];
        foreach ($found as $file) {
            if (isset($files[$file->url])) {
                continue;
            }
            $files[$file->url] = $file;
            if (count($files) >= $this->maxFiles) {
                break;
            }
        }

        return array_values($files);
    }

    /**
     * Keeps relative and http(s) URLs (after removing what browsers ignore); '' for anything else.
     */
    public static function safeUrl(string $url): string
    {
        $url = str_replace(["\t", "\n", "\r"], '', trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), "\x00..\x20\x7f"));
        if ($url === '' || strlen($url) > 2048 || preg_match('~^[\\\\/][\\\\/]~', $url) === 1) {
            return '';
        }
        if (preg_match('~^([^/?#]*):~', $url, $m) === 1) {
            return preg_match('~^https?$~i', $m[1]) === 1 ? $url : '';
        }

        return $url;
    }

    /**
     * The shown name: no control or invisible format characters (Cc / Cf: bidi overrides, zero-width, BOM), single spaces, at most 200 characters.
     */
    public static function name(string $value): string
    {
        $clean = (string) preg_replace('/\s+/u', ' ', $value);
        $clean = trim((string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', $clean));

        return trim(mb_substr($clean, 0, self::MAX_NAME));
    }

    /**
     * The attributes of a directive, parsed like Milkdown's `DirectiveAttributes::parse()` so the list keeps the URL the
     * page shows: tokens from the start, the first unparsable one ends the list, a repeated key keeps its last value.
     *
     * @return array<string, string>
     */
    private function attributes(string $source): array
    {
        $out    = [];
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length && count($out) < 20) {
            if (preg_match(self::TOKEN, $source, $m, PREG_UNMATCHED_AS_NULL, $offset) !== 1) {
                break;
            }
            $offset += strlen($m[0]);
            if ($m[3] !== null) {
                $out[$m[3]] = mb_substr($m[4] ?? $m[5] ?? $m[6] ?? '', 0, 500);
            }
        }

        return $out;
    }
}
