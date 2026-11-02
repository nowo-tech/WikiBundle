<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\TextConverter;
use League\HTMLToMarkdown\ElementInterface;

use function preg_replace_callback;
use function str_replace;
use function strtolower;

/**
 * Text of an HTML revision converted to the Milkdown dialect (1.13): the dialect tokens a Tiptap page holds as plain
 * text — `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` inserted by the suggestion hook and `@handle` mentions — are
 * written back unescaped (league/html-to-markdown escapes `[`, `]` and `_`), so they stay page links and mentions.
 */
final class WikiDialectTextConverter extends TextConverter
{
    private const PAGE_LINK = '/\\\\\[\\\\\[wiki:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})(#[a-z0-9][a-z0-9-]{0,80})?\\\\\]\\\\\]/';

    private const MENTION = '/(?<![^\s(])@((?:[a-zA-Z0-9.-]|\\\\_)+)/';

    public function convert(ElementInterface $element): string
    {
        $markdown = parent::convert($element);
        $markdown = preg_replace_callback(self::PAGE_LINK, static fn (array $m): string => '[[wiki:' . strtolower($m[1]) . ($m[2] ?? '') . ']]', $markdown) ?? $markdown;

        return preg_replace_callback(self::MENTION, static fn (array $m): string => '@' . str_replace('\\_', '_', $m[1]), $markdown) ?? $markdown;
    }
}
