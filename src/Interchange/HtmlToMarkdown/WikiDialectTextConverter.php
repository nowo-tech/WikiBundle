<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\TextConverter;
use League\HTMLToMarkdown\ElementInterface;
use Nowo\WikiBundle\Markdown\WikiHeadingAnchor;

use function preg_replace_callback;
use function str_replace;
use function strtolower;

/**
 * Text of an HTML revision converted to the Milkdown dialect (1.13): the dialect tokens a Tiptap page holds as plain
 * text — `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` inserted by the suggestion hook and `@handle` mentions — are
 * written back unescaped (league/html-to-markdown escapes `[`, `]` and `_`), so they stay page links and mentions.
 * 1.40: the anchor follows the heading id grammar ({@see WikiHeadingAnchor}).
 */
final class WikiDialectTextConverter extends TextConverter
{
    private const PAGE_LINK = '/\\\\\[\\\\\[wiki:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})(?:#([^\]\s#\\\\]{1,200}))?\\\\\]\\\\\]/';

    private const MENTION = '/(?<![^\s(])@((?:[a-zA-Z0-9.-]|\\\\_)+)/';

    public function convert(ElementInterface $element): string
    {
        $markdown = parent::convert($element);
        $markdown = preg_replace_callback(self::PAGE_LINK, static function (array $m): string {
            // 1.40: the heading id grammar; an invalid anchor is dropped and the page link kept.
            $anchor = WikiHeadingAnchor::normalise($m[2] ?? '');

            return '[[wiki:' . strtolower($m[1]) . ($anchor !== '' ? '#' . $anchor : '') . ']]';
        }, $markdown) ?? $markdown;

        return preg_replace_callback(self::MENTION, static fn (array $m): string => '@' . str_replace('\\_', '_', $m[1]), $markdown) ?? $markdown;
    }
}
