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
use Nowo\WikiBundle\History\WikiActorLabels;
use Nowo\WikiBundle\Repository\WikiPageMetadata;
use Nowo\WikiBundle\Repository\WikiPageMetadataFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageMetadataQuery;
use Nowo\WikiBundle\Repository\WikiPageProvenanceFinderInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\User\WikiUserId;

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
        private ?WikiPageProvenanceFinderInterface $provenance = null,
        private ?WikiActorLabels $actorLabels = null,
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
        $shown = array_slice($visible, $page * WikiApiQuery::PAGE_SIZE, WikiApiQuery::PAGE_SIZE);
        // Spec 234: creators and last-write provenance of the shown pages, in a fixed number of queries.
        $provenance = $this->provenance?->pageProvenance(array_map(static fn (WikiPageMetadata $row): string => $row->page->getId(), $shown)) ?? [];
        $ids        = array_map(static fn (WikiPageMetadata $row): ?string => $row->authorId, $shown);
        foreach ($provenance as $row) {
            $ids[] = $row->creatorId;
        }
        $authors = $this->users->byIds(array_values(array_filter($ids)));
        $pages   = [];
        foreach ($shown as $row) {
            $author  = $row->authorId === null ? null : ($authors[$row->authorId] ?? null);
            $origin  = $provenance[$row->page->getId()] ?? null;
            $creator = $origin?->creatorId === null ? null : ($authors[$origin->creatorId] ?? null);
            $people  = $this->people->presentAll($principal, [$author, $creator], $row->page);
            $pages[] = $this->serializer->pageHead($wiki, $row->page, $row->revisionNumber, $row->revisedAt, $people[0], $people[1], $origin?->channel, $this->actorLabels?->visibleOnPage($origin?->actorLabel, $author, $row->page, $principal->user));
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
        // Spec 234: the creator (the oldest revision's author) and the provenance of the last write.
        $origin  = $revision instanceof WikiPageRevision ? ($this->provenance?->pageProvenance([$page->getId()])[$page->getId()] ?? null) : null;
        $creator = match (true) {
            $origin?->creatorId === null                                   => null,
            $origin->creatorId === WikiUserId::of($revision?->getAuthor()) => $revision?->getAuthor(),
            default                                                        => $this->users->byIds([$origin->creatorId])[$origin->creatorId] ?? null,
        };
        $people = $this->people->presentAll($principal, [$revision?->getAuthor(), $creator], $page);
        $head   = $this->serializer->pageHead($wiki, $page, $revision?->getRevisionNumber(), $revision?->getCreatedAt(), $people[0], $people[1], $revision?->getChannel(), $this->actorLabels?->visibleOnPage($revision?->getActorLabel(), $revision?->getAuthor(), $page, $principal->user));
        if (!$revision instanceof WikiPageRevision) {
            return $head + ['format' => WikiPageRevision::FORMAT_MARKDOWN, 'content_view' => $this->content->view($principal, $page), 'content_markdown' => ''];
        }

        return $head + $this->content->revision($principal, $page, $revision, $this->content->view($principal, $page));
    }
}
