<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use RuntimeException;

use function array_slice;
use function count;
use function in_array;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Computes a simple line diff between two HTML revisions.
 */
final readonly class WikiRevisionDiffService
{
    public function __construct(
        private WikiPageRevisionRepositoryInterface $revisionRepository,
    ) {
    }

    /**
     * @return list<array{kind: string, line: string}>
     */
    public function diff(WikiPageRevision $from, WikiPageRevision $to): array
    {
        $fromLines = $this->htmlToLines($from->getContentHtml());
        $toLines   = $this->htmlToLines($to->getContentHtml());

        return $this->lineDiff($fromLines, $toLines);
    }

    public function findRevisionOrFail(string $revisionId): WikiPageRevision
    {
        $revision = $this->revisionRepository->findById($revisionId);
        if (!$revision instanceof WikiPageRevision) {
            throw new RuntimeException('Revision not found.');
        }

        return $revision;
    }

    /**
     * @return list<string>
     */
    private function htmlToLines(string $html): array
    {
        $text  = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</h1>', '</h2>', '</h3>'], "\n", $html)));
        $text  = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];

        return array_values(array_filter(array_map(trim(...), $lines), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param list<string> $fromLines
     * @param list<string> $toLines
     *
     * @return list<array{kind: string, line: string}>
     */
    private function lineDiff(array $fromLines, array $toLines): array
    {
        $result    = [];
        $fromCount = count($fromLines);
        $toCount   = count($toLines);
        $i         = 0;
        $j         = 0;

        while ($i < $fromCount || $j < $toCount) {
            $fromLine = $fromLines[$i] ?? null;
            $toLine   = $toLines[$j] ?? null;

            if ($fromLine === $toLine && $fromLine !== null) {
                $result[] = ['kind' => 'same', 'line' => $fromLine];
                ++$i;
                ++$j;
                continue;
            }

            if ($toLine !== null && ($fromLine === null || !in_array($toLine, array_slice($fromLines, $i), true))) {
                $result[] = ['kind' => 'add', 'line' => $toLine];
                ++$j;
                continue;
            }

            if ($fromLine !== null) {
                $result[] = ['kind' => 'remove', 'line' => $fromLine];
                ++$i;
            }
        }

        return $result;
    }
}
