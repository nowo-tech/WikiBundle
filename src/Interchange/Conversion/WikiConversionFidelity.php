<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\Conversion;

/**
 * What an HTML → Markdown conversion of one page lost or approximated (1.13, `wiki:convert-markdown` report).
 *
 * `lost` and `approximated` count constructs by code (see docs/USAGE.md#conversion-report); `text` compares the words
 * of the HTML with the words of the converted Markdown once rendered (page links and icons left out on both sides).
 */
final readonly class WikiConversionFidelity
{
    /**
     * @param array<string, int> $lost construct code => occurrences dropped by the conversion
     * @param array<string, int> $approximated construct code => occurrences kept in another form
     * @param float $similarity word similarity of source and result, 0 … 1 (1 = the same words)
     * @param int $wordsMissing words of the HTML missing from the rendered Markdown
     * @param int $wordsAdded words of the rendered Markdown missing from the HTML
     */
    public function __construct(
        public array $lost = [],
        public array $approximated = [],
        public float $similarity = 1.0,
        public int $wordsMissing = 0,
        public int $wordsAdded = 0,
    ) {
    }

    /**
     * Whether the rendered Markdown has exactly the words of the HTML.
     */
    public function textPreserved(): bool
    {
        return $this->wordsMissing === 0 && $this->wordsAdded === 0;
    }

    /**
     * Whether nothing was lost: no lost construct and the same words.
     */
    public function isLossless(): bool
    {
        return $this->lost === [] && $this->textPreserved();
    }

    /**
     * The report entry of the page.
     *
     * @return array{lost: array<string, int>, approximated: array<string, int>, text: array{preserved: bool, similarity: float, words_missing: int, words_added: int}}
     */
    public function toArray(): array
    {
        return [
            'lost'         => $this->lost,
            'approximated' => $this->approximated,
            'text'         => [
                'preserved'     => $this->textPreserved(),
                'similarity'    => $this->similarity,
                'words_missing' => $this->wordsMissing,
                'words_added'   => $this->wordsAdded,
            ],
        ];
    }
}
