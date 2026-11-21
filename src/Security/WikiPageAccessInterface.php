<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * 1.51: whether a person may read a page, by ids (public service, SemVer; REQ-ARCH-001 rule 1, DDD-host checklist B8).
 * The id twin of {@see WikiSpaceGuard::isPageReadable()}: the same rule ({@see WikiPageVisibility}: `View` on the page
 * **and every ancestor**, through the same space checker, a parent cycle reading as hidden), the same decisions.
 *
 * The user is a {@see WikiUserRef}: the security user it was built from is asked as such; a reference built from ids is
 * asked as the bundle's own stand-in user (the id operations do the same). An unknown page id, or one that differs from
 * the stored id in any byte, is not readable. Pages and their ancestors are read fresh from the database in one query per
 * tree level (per 500 pages), never kept between calls (FrankenPHP worker mode with the kernel kept alive).
 *
 * **Readable is not live.** Like the guard, the rule does not look at the page's archive state: an archived or trashed
 * page (the trash archives a page and its sub-tree) of an active space is readable by anyone with `View`. A caller that
 * passes raw ids (stored references, embeddings) filters archived and trashed pages itself:
 * {@see \Nowo\WikiBundle\Query\WikiPageQueryInterface::findPagesByIds()} (`archivedAt === null`) or
 * {@see \Nowo\WikiBundle\Query\WikiArchiveFilter::Active}. The backlinks and the search of the read port only ever
 * return live pages.
 */
interface WikiPageAccessInterface
{
    /**
     * Whether the user may read the page.
     */
    public function isPageReadable(WikiUserRef $user, string $pageId): bool;

    /**
     * The pages of the list the user may read (search hits, AI sources, backlinks): one decision per page with the same
     * rule, each branch walked once.
     *
     * @param list<string> $pageIds
     *
     * @return list<string> the readable ones, distinct, in the order asked
     */
    public function readablePageIds(WikiUserRef $user, array $pageIds): array;
}
