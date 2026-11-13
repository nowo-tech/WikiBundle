<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @return array<int, array{author: string, named: bool, avatar: string|null, label: string|null, saves: int, deleted: bool}>
     *                                                                                                                            per revision number; `saves` is the size of the session on its newest row, 0 on the others;
     *                                                                                                                            `avatar` (1.16) the author's picture URL, only beside a real name; `deleted` (1.26) true when
     *                                                                                                                            the author's account was deleted (`author` is then '': the page shows "Former person")
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
        // 1.26: a deleted author (null) is not presented: nothing is kept about them, the row reads "Former person".
        $live = [];
        foreach ($revisions as $revision) {
            $author = $revision->getAuthor();
            if ($author !== null) {
                $live[] = $author;
            }
        }
        $authors = $this->authors->presentAll($live, $page);
        $rows    = [];
        $index   = 0;
        foreach ($revisions as $revision) {
            $number = $revision->getRevisionNumber();
            $author = $revision->getAuthor() !== null
                ? $authors[$index++] + ['deleted' => false]
                : ['name' => '', 'named' => false, 'avatarUrl' => null, 'deleted' => true];
            $rows[$number] = [
                'author'  => $author['name'],
                'named'   => $author['named'],
                'avatar'  => $author['avatarUrl'],
                'label'   => $labels[$revision->getId()] ?? null,
                'saves'   => $saves[$number] ?? 0,
                'deleted' => $author['deleted'],
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
        // 1.26: a revision of a deleted author is a session of its own (two former people cannot be told apart).
        $newerAuthor = $newer->getAuthor();
        $olderAuthor = $older->getAuthor();
        if ($newerAuthor === null || $olderAuthor === null || $this->identifier($newerAuthor) !== $this->identifier($olderAuthor)) {
            return true;
        }

        return $newer->getCreatedAt()->getTimestamp() - $older->getCreatedAt()->getTimestamp() > $this->sessionMinutes * 60;
    }

    private function identifier(object $author): string
    {
        return match (true) {
            $author instanceof UserInterface     => $author->getUserIdentifier(),
            $author instanceof WikiStoredUserRef => "\0id:" . $author->id, // 1.32: database.user_reference: id
            default                              => (string) spl_object_id($author),
        };
    }
}
