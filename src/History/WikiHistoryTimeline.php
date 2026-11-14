<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function spl_object_id;

/**
 * The history timeline of a page (1.15, `history.timeline`): for each revision its author label, its named-version
 * label and its **session** — consecutive saves by the same author less than `history.session_minutes` apart. A named
 * version is a session of its own. The first (newest) revision of a session carries the number of saves in it.
 *
 * Spec 234: each row also carries the author's host badges (one provider call per page of rows) and the
 * write's provenance (channel, actor label; the label never for a deleted author).
 */
final readonly class WikiHistoryTimeline
{
    public function __construct(
        private WikiRevisionLabelRepositoryInterface $labels,
        private WikiAuthorLabel $authors,
        private int $sessionMinutes = 30,
        private ?WikiMemberBadges $badges = null,
        private ?WikiActorLabels $actorLabels = null,
    ) {
    }

    /**
     * @param list<WikiPageRevision> $revisions newest first (one page of the history)
     * @param object|null $viewer spec 234: who reads the history (sees the actor labels of their own writes)
     *
     * @return array<int, array{author: string, named: bool, avatar: string|null, label: string|null, saves: int, deleted: bool, badges: list<WikiMemberBadge>, channel: string|null, actor_label: string|null}>
     *                                                                                                                                                                                                           per revision number; `saves` is the size of the session on its newest row, 0 on the others;
     *                                                                                                                                                                                                           `avatar` (1.16) the author's picture URL, only beside a real name; `deleted` (1.26) true when
     *                                                                                                                                                                                                           the author's account was deleted (`author` is then '': the page shows "Former person");
     *                                                                                                                                                                                                           spec 234: `badges`, `channel`, `actor_label` (only as {@see WikiActorLabels} allows: audit PQ-M1)
     */
    public function rows(WikiPage $page, array $revisions, ?object $viewer = null): array
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
        // Spec 234: one badge provider call per page of rows.
        $this->badges?->prime($live);
        $rows  = [];
        $index = 0;
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
                // Spec 234: host badges and the write's provenance (nothing kept about a deleted author).
                'badges'      => $this->badges?->of($revision->getAuthor()) ?? [],
                'channel'     => $revision->getChannel(),
                'actor_label' => $this->actorLabels?->visible($revision->getActorLabel(), $revision->getAuthor(), $author['named'], $viewer),
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
        return $author instanceof UserInterface ? $author->getUserIdentifier() : (string) spl_object_id($author);
    }
}
