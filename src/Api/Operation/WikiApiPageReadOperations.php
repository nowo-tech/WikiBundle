<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Repository\WikiApiUsersReader;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\Serializer\WikiApiPeople;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageMetadata;
use Nowo\WikiBundle\Repository\WikiPageMetadataFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageMetadataQuery;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;

/**
 * 1.24 (spec 218 FR-010, FR-012 – FR-014, FR-019): page reads. Lists carry metadata only, read in a fixed number of
 * queries, each row checked for the person (the page and every ancestor) before it is counted.
 */
final readonly class WikiApiPageReadOperations
{
    public function __construct(
        private WikiApiGuard $guard,
        private WikiPageMetadataFinderInterface $metadata,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiApiUsersReader $users,
        private WikiApiPeople $people,
        private WikiApiContent $content,
        private WikiApiSerializer $serializer,
    ) {
    }

    /**
     * `GET /wikis/{wiki}/pages`.
     *
     * @param string|null $parent a page id, `root` for the top level, null for every page
     *
     * @throws WikiApiRefusal
     *
     * @return array{pages: list<array<string, mixed>>, last_page: bool}
     */
    public function list(WikiApiPrincipal $principal, string $slug, ?string $parent, ?bool $archived, ?DateTimeImmutable $updatedSince, int $page): array
    {
        $wiki  = $this->guard->wiki($principal, $slug);
        $space = $this->guard->openSpace($wiki);
        if (!$space instanceof WikiSpace) {
            return ['pages' => [], 'last_page' => true];
        }
        $this->guard->require($principal, WikiAction::View, $space);
        if ($archived === true && !WikiApiCapability::allows($principal->level, WikiAction::Archive)) {
            throw WikiApiRefusal::levelRefused();
        }
        $parentId = $parent === null || $parent === 'root' ? null : $this->guard->page($principal, $wiki, $parent)->getId();

        $rows      = $this->metadata->pageMetadata($space, new WikiPageMetadataQuery($archived === true, $parentId, $parent === 'root', $updatedSince));
        $decisions = [];
        $visible   = [];
        foreach ($rows as $row) {
            if (WikiPageVisibility::isReadable($this->checker, $principal->user, $space, $row->page, $decisions)
                && (!$row->page->isArchived() || $this->guard->maySeeArchived($principal, $space, $row->page))) {
                $visible[] = $row;
            }
        }
        $shown   = array_slice($visible, $page * WikiApiQuery::PAGE_SIZE, WikiApiQuery::PAGE_SIZE);
        $authors = $this->users->byIds(array_values(array_filter(array_map(static fn (WikiPageMetadata $row): ?string => $row->authorId, $shown))));
        $pages   = [];
        foreach ($shown as $row) {
            $author  = $row->authorId === null ? null : ($authors[$row->authorId] ?? null);
            $pages[] = $this->serializer->pageHead($wiki, $row->page, $row->revisionNumber, $row->revisedAt, $this->people->present($principal, $author, $row->page));
        }

        return ['pages' => $pages, 'last_page' => count($visible) <= ($page + 1) * WikiApiQuery::PAGE_SIZE];
    }

    /**
     * `GET /wikis/{wiki}/pages/{page}`: the page with its current body in this token's view.
     *
     * @throws WikiApiRefusal
     *
     * @return array<string, mixed>
     */
    public function get(WikiApiPrincipal $principal, string $slug, string $pageId): array
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId, true);
        $this->guard->require($principal, WikiAction::View, $page->getSpace(), $page);

        return $this->body($principal, $wiki, $page);
    }

    /**
     * A page body (also the answer of the writes).
     *
     * @return array<string, mixed>
     */
    public function body(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPage $page): array
    {
        $revision = $page->getCurrentRevision();
        $head     = $this->serializer->pageHead($wiki, $page, $revision?->getRevisionNumber(), $revision?->getCreatedAt(), $this->people->present($principal, $revision?->getAuthor(), $page));
        if (!$revision instanceof WikiPageRevision) {
            return $head + ['format' => WikiPageRevision::FORMAT_MARKDOWN, 'content_view' => $this->content->view($principal, $page), 'content_markdown' => ''];
        }

        return $head + $this->content->revision($principal, $page, $revision, $this->content->view($principal, $page));
    }
}
