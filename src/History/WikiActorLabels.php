<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Spec 234 (audit PQ-M1): when a write's actor label (a token name) may be shown or answered. The label is a
 * per-person string that is the same on every page, so beside an opaque "Editor xxxx" it would link and often name the
 * pseudonymous author. It is shown only:
 *
 * - to the author themself (their own token name; the same object, or an accepted user class with the author's id), or
 * - with `history.show_actor_labels` on (default: `history.show_names`) **and** beside an author shown under a real
 *   name ({@see WikiAuthorLabel}) **and** when the label passes the same safe-name rule as real names
 *   ({@see WikiCollabService::isSafeName()}: no `@`, no login or local part, printable Latin text only).
 *
 * A deleted author's label is never shown.
 */
final readonly class WikiActorLabels
{
    /**
     * @param WikiUserClassPolicy|null $classes which viewer classes map to a stored user by id (audit PQ-D1-L1, JX-W1);
     *                                          null: only the very same object is the author
     */
    public function __construct(
        private WikiAuthorLabel $authors,
        private bool $show = false,
        private ?WikiUserClassPolicy $classes = null,
    ) {
    }

    /**
     * @param string|null $label the stored actor label
     * @param object|null $author the revision author (null: deleted)
     * @param bool $named whether the author is shown under a real name
     * @param object|null $viewer who reads it (null: nobody in particular)
     */
    public function visible(?string $label, ?object $author, bool $named, ?object $viewer = null): ?string
    {
        if ($label === null || $author === null) {
            return null;
        }
        if ($this->isSamePerson($author, $viewer)) {
            return $label;
        }
        if (!$this->show || !$named) {
            return null;
        }

        return WikiCollabService::isSafeName($label, $author instanceof UserInterface ? $author->getUserIdentifier() : '') ? $label : null;
    }

    /**
     * {@see self::visible()} for an author whose presentation on the page is not known yet.
     */
    public function visibleOnPage(?string $label, ?object $author, WikiPage $page, ?object $viewer = null): ?string
    {
        if ($label === null || $author === null) {
            return null;
        }
        $named = $this->show && !$this->isSamePerson($author, $viewer) && $this->authors->present($author, $page)['named'];

        return $this->visible($label, $author, $named, $viewer);
    }

    /**
     * The same person: the same object, or a viewer of `user_class` or an accepted class whose usable id equals the
     * author's (exact), as {@see WikiCommentService} matches comment owners (audit JX-W1, PQ-D1-L1): Admin#5 of another
     * identity domain is never Customer#5.
     */
    private function isSamePerson(object $author, ?object $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }
        if ($author === $viewer) {
            return true;
        }
        $id = $this->classes?->idOf($viewer);

        return $id !== null && (string) $id === WikiUserId::of($author);
    }
}
