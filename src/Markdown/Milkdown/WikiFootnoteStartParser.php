<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Extension\Footnote\Parser\FootnoteParser;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Reference\Reference;

use function mb_strlen;
use function mb_substr;
use function preg_match;

/**
 * `[^label]: text` footnote definitions (1.16.2, audit EP-I6). league/commonmark's own start parser advances the cursor
 * by the definition's **byte** length while the cursor counts characters, so a non-ASCII label (`[^Ä]: Note`) ate
 * one character of the note per extra byte (`ote`). This one is registered before it and advances by characters;
 * otherwise it is the same.
 */
final readonly class WikiFootnoteStartParser implements BlockStartParserInterface
{
    private const PATTERN = '/^\[\^([^\s^\]]+)\]\:(?:\s|$)/u';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $parserState->getLastMatchedBlockParser()->canHaveLazyContinuationLines()) {
            return BlockStart::none();
        }

        if (preg_match(self::PATTERN, mb_substr($cursor->getLine(), $cursor->getNextNonSpacePosition(), null, 'UTF-8'), $match) !== 1) {
            return BlockStart::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();
        $cursor->advanceBy(mb_strlen($match[0], 'UTF-8'));

        return BlockStart::of(new FootnoteParser(new Reference($match[1], $match[1], $match[1])))->at($cursor);
    }
}
