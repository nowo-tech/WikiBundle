<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

/**
 * Finds the references of a Markdown document with the same parser that renders it (1.13): what the renderer shows as
 * a page link or a mention is exactly what a link index gets, never a match inside code or an e-mail address.
 *
 * {@see WikiMarkdownReferenceExtractor} uses it when the renderer implements it (the Milkdown bridge does).
 */
interface WikiMarkdownReferenceParserInterface
{
    /**
     * Returns the page links and mentions of the document, each list distinct and in order of first appearance.
     *
     * @param string $markdown Markdown source
     *
     * @return array{pages: list<array{id: string, anchor: string|null}>, mentions: list<string>} page ids (lower case)
     *                                                                                            with their heading
     *                                                                                            anchor, and mention
     *                                                                                            handles (without @)
     */
    public function references(string $markdown): array;
}
