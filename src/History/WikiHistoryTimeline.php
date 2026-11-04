<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function spl_object_id;

/**
 * The history timeline of a page (1.15, `history.timeline`): for each revision its author label, its named-version
 * label and its **session** — consecutive saves by the same author less than `history.session_minutes` apart. A named
 * version is a session of its own. The first (newest) revision of a session carries the number of saves in it.
 */
final readonly class WikiHistoryTimeline
{
    public function __construct(
        private WikiRevisionLabelRepositoryInterface $labels,
        private WikiAuthorLabel $authors,
        private int $sessionMinutes = 30,
    ) {
    }

    /**
     * @param list<WikiPageRevision> $revisions newest first (one page of the history)
     *
     * @return array<int, array{author: string, named: bool, avatar: string|null, label: string|null, saves: int}> per revision
     *                                                                                                             number; `saves` is the size of the session on its newest row, 0 on the others; `avatar` (1.16) the
     *                                                                                                             author's picture URL, only beside a real name
     */
    public function rows(WikiPage $page, array $revisions): array
    {
        $labels = $this->labels->labelsFor($revisions);
        $saves  = [];
        $start  = 0;
        $prev   = null;
        foreach ($revisions as $revision) {
            $number = $revision->getRevisionNumber();
            if (!$prev instanceof WikiPageRevision || $this->breaks($prev, $revision, $labels)) {
                $start = $number;
            }
            $saves[$start] = ($saves[$start] ?? 0) + 1;
            $prev          = $revision;
        }

        // 1.16: one avatar resolver call per page of rows, for the authors shown under their real name only.
        $authors = $this->authors->presentAll(array_map(static fn (WikiPageRevision $revision): object => $revision->getAuthor(), $revisions), $page);
        $rows    = [];
        foreach ($revisions as $index => $revision) {
            $number        = $revision->getRevisionNumber();
            $rows[$number] = [
                'author' => $authors[$index]['name'],
                'named'  => $authors[$index]['named'],
                'avatar' => $authors[$index]['avatarUrl'],
                'label'  => $labels[$revision->getId()] ?? null,
                'saves'  => $saves[$number] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $labels
     */
    private function breaks(WikiPageRevision $newer, WikiPageRevision $older, array $labels): bool
    {
        if (isset($labels[$newer->getId()]) || isset($labels[$older->getId()])) {
            return true;
        }
        if ($this->identifier($newer->getAuthor()) !== $this->identifier($older->getAuthor())) {
            return true;
        }

        return $newer->getCreatedAt()->getTimestamp() - $older->getCreatedAt()->getTimestamp() > $this->sessionMinutes * 60;
    }

    private function identifier(object $author): string
    {
        return $author instanceof UserInterface ? $author->getUserIdentifier() : (string) spl_object_id($author);
    }
}
