<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;

/**
 * 1.24 (spec 218 FR-034 – FR-036): comment writes through the comment service, which keeps its own rights, caps, rate
 * limit (shared with the UI) and events. The API adds the level: commenting, resolving and changing one's own
 * comments at Edit; deleting anyone's comment and whole threads only at Full with `ModerateComments`. New threads are
 * page-level: inline anchors need the editor's position data and stay in the UI (FR-035). Others' comments are never
 * edited through the API.
 */
final readonly class WikiApiCommentWriteOperations
{
    public function __construct(
        private WikiApiGuard $guard,
        private WikiApiComments $comments,
        private WikiApiContent $content,
        private WikiApiAudit $audit,
        private ?WikiCommentRepositoryInterface $repository = null,
    ) {
    }

    /**
     * `POST …/threads` `{body}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function create(WikiApiPrincipal $principal, string $slug, string $pageId, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Comment);
        $body          = $input->string('body', true) ?? '';
        $input->ignore('anchor');
        $this->audit->requireAudit();
        $created = $this->comments->call(fn (): array => $this->comments->service()->create($principal->user, $page->getSpace(), $page, null, $body));
        $id      = (string) $created['id'];
        $this->audit->written($principal, 'thread.create', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $id], ['body']);

        return [$this->thread($principal, $page, $id), $wiki, $page];
    }

    /**
     * `POST …/threads/{thread}/comments` `{body}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function reply(WikiApiPrincipal $principal, string $slug, string $pageId, string $threadId, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Comment);
        $body          = $input->string('body', true) ?? '';
        $this->audit->requireAudit();
        $this->comments->call(fn (): array => $this->comments->service()->reply($principal->user, $page->getSpace(), $page, $threadId, $body));
        $this->audit->written($principal, 'comment.create', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $threadId], ['body']);

        return [$this->thread($principal, $page, $threadId), $wiki, $page];
    }

    /**
     * `PATCH …/threads/{thread}` `{resolved}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function resolve(WikiApiPrincipal $principal, string $slug, string $pageId, string $threadId, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::ResolveComment);
        $resolved      = $input->bool('resolved') ?? throw WikiApiRefusal::invalid('resolved');
        $input->ignore('anchor');
        $this->audit->requireAudit();
        $this->comments->call(fn (): array => $this->comments->service()->patchThread($principal->user, $page->getSpace(), $page, $threadId, ['resolved' => $resolved]));
        $this->audit->written($principal, $resolved ? 'thread.resolve' : 'thread.reopen', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $threadId], ['resolved']);

        return [$this->thread($principal, $page, $threadId), $wiki, $page];
    }

    /**
     * `PATCH …/comments/{comment}` `{body}`: one's own comment only.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function edit(WikiApiPrincipal $principal, string $slug, string $pageId, string $commentId, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Comment);
        $comment       = $this->comment($page, $commentId);
        if (!$this->guard->isUser($principal, $comment->getAuthor())) {
            throw WikiApiRefusal::permissionRefused();
        }
        $body = $input->string('body', true) ?? '';
        $this->audit->requireAudit();
        $this->comments->call(fn (): array => $this->comments->service()->editComment($principal->user, $page->getSpace(), $page, $commentId, $body));
        $threadId = $comment->getThread()->getId();
        $this->audit->written($principal, 'comment.edit', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $threadId, 'comment' => $commentId], ['body']);

        return [$this->thread($principal, $page, $threadId), $wiki, $page];
    }

    /**
     * `DELETE …/comments/{comment}`: one's own at Edit; anyone's at Full with `ModerateComments`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: WikiApiWiki, 1: WikiPage}
     */
    public function delete(WikiApiPrincipal $principal, string $slug, string $pageId, string $commentId): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Comment);
        $comment       = $this->comment($page, $commentId);
        if (!$this->guard->isUser($principal, $comment->getAuthor())) {
            $this->guard->require($principal, WikiAction::ModerateComments, $page->getSpace(), $page);
        }
        $this->audit->requireAudit();
        $this->comments->call(fn (): ?array => $this->comments->service()->deleteComment($principal->user, $page->getSpace(), $page, $commentId));
        $this->audit->written($principal, 'comment.delete', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $comment->getThread()->getId(), 'comment' => $commentId], []);

        return [$wiki, $page];
    }

    /**
     * `DELETE …/threads/{thread}`: Full with `ModerateComments`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: WikiApiWiki, 1: WikiPage}
     */
    public function deleteThread(WikiApiPrincipal $principal, string $slug, string $pageId, string $threadId): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::ModerateComments);
        $this->audit->requireAudit();
        $this->comments->call(function () use ($principal, $page, $threadId): void {
            $this->comments->service()->deleteThread($principal->user, $page->getSpace(), $page, $threadId);
        });
        $this->audit->written($principal, 'thread.delete', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['thread' => $threadId], []);

        return [$wiki, $page];
    }

    /**
     * The page a comment write targets: reach, the archived wiki, then level and person for the action.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: WikiApiWiki, 1: WikiPage}
     */
    private function target(WikiApiPrincipal $principal, string $slug, string $pageId, WikiAction $action): array
    {
        $this->comments->service();
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId);
        $this->guard->requireWritable($wiki);
        if (!WikiApiCapability::allows($principal->level, $action)) {
            throw WikiApiRefusal::levelRefused();
        }
        $this->guard->require($principal, WikiAction::ViewComments, $page->getSpace(), $page);
        $this->guard->require($principal, $action, $page->getSpace(), $page);

        return [$wiki, $page];
    }

    /** @throws WikiApiRefusal */
    private function comment(WikiPage $page, string $commentId): WikiComment
    {
        $comment = $this->repository?->comment($page, $commentId);
        if (!$comment instanceof WikiComment || $comment->isDeleted()) {
            throw WikiApiRefusal::notFound();
        }

        return $comment;
    }

    /**
     * @throws WikiApiRefusal
     *
     * @return array<string, mixed>
     */
    private function thread(WikiApiPrincipal $principal, WikiPage $page, string $threadId): array
    {
        $one = $this->comments->call(fn (): array => $this->comments->service()->threadEntity($principal->user, $page->getSpace(), $page, $threadId));

        return $this->comments->thread($principal, $page, $one['thread'], $one['comments'], $this->content->view($principal, $page));
    }
}
