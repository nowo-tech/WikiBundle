<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;

/**
 * 1.24 (spec 218 FR-034): thread reads, through the comment service's own rights check (`ViewComments`).
 */
final readonly class WikiApiCommentReadOperations
{
    public function __construct(
        private WikiApiGuard $guard,
        private WikiApiComments $comments,
        private WikiApiContent $content,
    ) {
    }

    /**
     * `GET …/threads`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{threads: list<array<string, mixed>>, truncated: bool}
     */
    public function threads(WikiApiPrincipal $principal, string $slug, string $pageId): array
    {
        $page   = $this->page($principal, $slug, $pageId);
        $all    = $this->comments->call(fn (): array => $this->comments->service()->threadEntities($principal->user, $page->getSpace(), $page));
        $view   = $this->content->view($principal, $page);
        $answer = [];
        foreach ($all['threads'] as $thread) {
            $answer[] = $this->comments->thread($principal, $page, $thread, $all['comments'][$thread->getId()] ?? [], $view);
        }

        return ['threads' => $answer, 'truncated' => $all['truncated']];
    }

    /**
     * `GET …/threads/{thread}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array<string, mixed>
     */
    public function thread(WikiApiPrincipal $principal, string $slug, string $pageId, string $threadId): array
    {
        $page = $this->page($principal, $slug, $pageId);
        $one  = $this->comments->call(fn (): array => $this->comments->service()->threadEntity($principal->user, $page->getSpace(), $page, $threadId));

        return $this->comments->thread($principal, $page, $one['thread'], $one['comments'], $this->content->view($principal, $page));
    }

    /** @throws WikiApiRefusal */
    private function page(WikiApiPrincipal $principal, string $slug, string $pageId): WikiPage
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId);
        $this->guard->require($principal, WikiAction::ViewComments, $page->getSpace(), $page);

        return $page;
    }
}
