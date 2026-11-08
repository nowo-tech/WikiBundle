<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Hook\WikiApiFilesInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiManagementInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\Value\WikiApiFile;
use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_keys;
use function array_map;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * 1.24 (spec 218 FR-033, FR-037 – FR-039, Beacon Q-B1): the endpoints whose work is the host's — wiki lifecycle and
 * files. The bundle checks reach, the archived wiki, the level and (for an existing space) the person, asks the audit
 * for writes, and hands the rest to the hooks.
 */
final readonly class WikiApiDelegatedOperations
{
    /** What a downloaded file may do in a browser: nothing (spec 212 FR-005). */
    public const DOWNLOAD_CSP = "default-src 'none'; sandbox";

    public function __construct(
        private WikiApiGuard $guard,
        private WikiApiWikiManagementInterface $management,
        private WikiApiFilesInterface $files,
        private WikiApiAudit $audit,
        private WikiApiSerializer $serializer,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * `POST /wikis` `{owner_type, owner_id, name, description}`: Full (audit IF-L7, IF-L8).
     *
     * The owner is the host's notion: the hook names its key ({@see WikiApiWikiManagementInterface::ownerKey()}), and an
     * owner outside the token's *where* is 404 before anything is created. The new wiki is judged by its owner, so a
     * restricted wiki this token just created is answered (later requests follow the usual reach, where a restricted
     * wiki must be named). A hook that answers a wiki of another owner is a host bug: the write is audited, an error
     * names the hook, the idempotent claim is kept so a retry does not create it again, and the answer is 500.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki}
     */
    public function createWiki(WikiApiPrincipal $principal, WikiApiInput $input): array
    {
        if (!WikiApiCapability::allows($principal->level, WikiAction::Manage)) {
            throw WikiApiRefusal::levelRefused();
        }
        $new = new WikiApiNewWiki(
            $input->string('owner_type', true, 32) ?? '',
            $input->string('owner_id', true, 64) ?? '',
            $this->name($input, true) ?? '',
            $input->string('description', false, 2000),
        );
        $ownerKey = $this->management->ownerKey($principal, $new);
        if ($ownerKey === null || !$principal->where->reachesOwner($ownerKey)) {
            throw WikiApiRefusal::notFound();
        }
        $this->audit->requireAudit();
        $wiki = $this->management->create($principal, $new);
        $this->audit->written($principal, 'wiki.create', $wiki->slug, $wiki->space?->getId(), null, [], ['name', 'description']);
        if ($wiki->ownerKey !== $ownerKey) {
            $this->logger->error('Wiki API: the wiki management hook created a wiki under another owner than the one asked.', ['bundle' => 'nowo_wiki', 'hook' => $this->management::class]);

            throw WikiApiRefusal::serverErrorAfterCommit();
        }

        return [$this->serializer->wiki($wiki), $wiki];
    }

    /**
     * `PATCH /wikis/{wiki}` `{name, description}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki}
     */
    public function updateWiki(WikiApiPrincipal $principal, string $slug, WikiApiInput $input): array
    {
        $wiki  = $this->manageable($principal, $slug, true);
        $patch = new WikiApiWikiPatch($this->name($input, false), $input->string('description', false, 2000));
        $this->audit->requireAudit();
        $wiki = $this->management->update($principal, $wiki, $patch);
        $this->audit->written($principal, 'wiki.update', $wiki->slug, $wiki->space?->getId(), null, [], array_keys(array_filter(['name' => $patch->name, 'description' => $patch->description], static fn (?string $v): bool => $v !== null)));

        return [$this->serializer->wiki($wiki), $wiki];
    }

    /**
     * `POST /wikis/{wiki}/archive` and `/unarchive`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki}
     */
    public function archiveWiki(WikiApiPrincipal $principal, string $slug, bool $archive): array
    {
        $wiki = $this->manageable($principal, $slug, $archive);
        $this->audit->requireAudit();
        $wiki = $archive ? $this->management->archive($principal, $wiki) : $this->management->unarchive($principal, $wiki);
        $this->audit->written($principal, $archive ? 'wiki.archive' : 'wiki.unarchive', $wiki->slug, $wiki->space?->getId(), null, [], ['archived']);

        return [$this->serializer->wiki($wiki), $wiki];
    }

    /**
     * `GET …/pages/{page}/files`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{files: list<array{uuid: string, name: string, kind: string, mime_type: string|null, size: int|null}>}
     */
    public function listFiles(WikiApiPrincipal $principal, string $slug, string $pageId): array
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId, true);
        $this->guard->require($principal, WikiAction::View, $page->getSpace(), $page);

        return ['files' => array_map(static fn (WikiApiFile $file): array => [
            'uuid'      => $file->uuid,
            'name'      => $file->name,
            'kind'      => $file->kind,
            'mime_type' => $file->mimeType,
            'size'      => $file->size,
        ], $this->files->list($principal, $wiki, $page))];
    }

    /**
     * `POST /wikis/{wiki}/files` (multipart, field `file`): Edit; every check of the file is the host's.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array{uuid: string, markdown: string}, 1: WikiApiWiki}
     */
    public function upload(WikiApiPrincipal $principal, string $slug, mixed $file): array
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $this->guard->requireWritable($wiki);
        $space = $this->guard->openSpace($wiki);
        if ($space instanceof WikiSpace) {
            $this->guard->require($principal, WikiAction::Edit, $space);
        } elseif (!WikiApiCapability::allows($principal->level, WikiAction::Edit)) {
            throw WikiApiRefusal::levelRefused();
        }
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw WikiApiRefusal::invalid('file');
        }
        $this->audit->requireAudit();
        $stored = $this->files->upload($principal, $wiki, $file);
        $this->audit->written($principal, 'file.upload', $wiki->slug, $space?->getId(), null, ['file' => $stored->uuid], []);

        return [['uuid' => $stored->uuid, 'markdown' => $stored->markdown], $wiki];
    }

    /**
     * `GET /wikis/{wiki}/files/{file}`: the host's answer, as an attachment the browser may not run.
     *
     * @throws WikiApiRefusal
     */
    public function download(WikiApiPrincipal $principal, string $slug, string $fileUuid): Response
    {
        $wiki  = $this->guard->wiki($principal, $slug);
        $space = $this->guard->openSpace($wiki);
        if ($space instanceof WikiSpace) {
            $this->guard->require($principal, WikiAction::View, $space);
        }
        $response = $this->files->download($principal, $wiki, $fileUuid) ?? throw WikiApiRefusal::notFound();
        if (!str_starts_with(strtolower(trim((string) $response->headers->get('Content-Disposition'))), 'attachment')) {
            $response->headers->set('Content-Disposition', 'attachment');
        }
        $response->headers->set('Content-Security-Policy', self::DOWNLOAD_CSP);

        return $response;
    }

    /**
     * A wiki whose lifecycle changes: Full; when its space exists, the person must open it (404 otherwise, as reads) and
     * hold `Manage` on it; only a wiki nobody opened yet is decided on the level, the rest being the host's (audit
     * IF-L7). Not archived, unless it is being unarchived.
     *
     * @throws WikiApiRefusal
     */
    private function manageable(WikiApiPrincipal $principal, string $slug, bool $mustBeActive): WikiApiWiki
    {
        $wiki = $this->guard->wiki($principal, $slug);
        if ($wiki->space instanceof WikiSpace && !$wiki->canOpen) {
            throw WikiApiRefusal::notFound();
        }
        if ($mustBeActive) {
            $this->guard->requireWritable($wiki);
        }
        if ($wiki->space instanceof WikiSpace) {
            $this->guard->require($principal, WikiAction::Manage, $wiki->space);
        } elseif (!WikiApiCapability::allows($principal->level, WikiAction::Manage)) {
            throw WikiApiRefusal::levelRefused();
        }

        return $wiki;
    }

    /** @throws WikiApiRefusal */
    private function name(WikiApiInput $input, bool $required): ?string
    {
        $name = $input->string('name', $required, 255);
        if ($name !== null && trim($name) === '') {
            throw WikiApiRefusal::invalid('name');
        }

        return $name === null ? null : trim($name);
    }
}
