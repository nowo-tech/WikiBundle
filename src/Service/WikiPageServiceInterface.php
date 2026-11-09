<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;

/**
 * Port of the page writes a host calls from its application layer (1.27; public API, SemVer). Aliased to
 * {@see WikiPageService}; type-hint this interface in your adapters and replace it with a double in your tests.
 *
 * `$author` / `$actor` may be the `user_class` entity or any user exposing the same `getId()` (see
 * {@see WikiUserReferenceResolverInterface}).
 */
interface WikiPageServiceInterface
{
    /**
     * Creates a page with its first revision; dispatches PAGE_SAVED and PAGE_CREATED.
     *
     * @throws InvalidArgumentException when the slug is invalid or taken, or the format is unknown
     */
    public function create(
        WikiSpace $space,
        string $title,
        string $contentHtml,
        object $author,
        ?WikiPage $parent = null,
        ?string $slug = null,
        string $format = WikiPageRevision::FORMAT_HTML,
    ): WikiPage;

    /**
     * Appends a revision (optionally against an expected revision number); dispatches PAGE_SAVED.
     */
    public function saveRevision(
        WikiPage $page,
        string $title,
        string $contentHtml,
        object $author,
        string $format = WikiPageRevision::FORMAT_HTML,
        ?int $expectedRevision = null,
    ): WikiPageRevision;

    /**
     * Appends a revision with the body of an older one; dispatches PAGE_SAVED and PAGE_RESTORED.
     */
    public function restore(WikiPage $page, WikiPageRevision $from, object $actor, ?int $expectedRevision = null): WikiPageRevision;

    /**
     * Archives a page (no-op when archived); dispatches PAGE_ARCHIVED.
     */
    public function archive(WikiPage $page, ?object $actor = null): void;

    /**
     * Unarchives a page (no-op when active); dispatches PAGE_UNARCHIVED.
     */
    public function unarchive(WikiPage $page, ?object $actor = null): void;
}
