<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\Serializer\WikiApiPeople;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;

use function array_map;
use function count;

/**
 * 1.24 (spec 218 FR-034): how threads read in the API, and how the comment service's refusals answer. Bodies follow the
 * page's view (source for whoever may edit it, else the reader's Markdown); quotes are the service's `visibleQuote()`;
 * people follow the history policy (audit IF-I5).
 */
final readonly class WikiApiComments
{
    public function __construct(
        private ?WikiCommentService $service,
        private WikiApiPeople $people,
        private WikiApiContent $content,
    ) {
    }

    /** The comment service; comments switched off: 404, as an unknown path. @throws WikiApiRefusal */
    public function service(): WikiCommentService
    {
        return $this->service ?? throw WikiApiRefusal::notFound();
    }

    /**
     * @param list<WikiComment> $comments
     *
     * @return array<string, mixed>
     */
    public function thread(WikiApiPrincipal $principal, WikiPage $page, WikiCommentThread $thread, array $comments, string $view): array
    {
        $people = $this->people->presentAll($principal, [...array_map(static fn (WikiComment $c): ?object => $c->isDeleted() ? null : $c->getAuthor(), $comments), $thread->getResolvedBy()], $page);
        $items  = [];
        foreach ($comments as $index => $comment) {
            $deleted = $comment->isDeleted();
            $items[] = [
                'id'            => $comment->getId(),
                'author'        => $people[$index],
                'created_at'    => WikiApiSerializer::ms($comment->getCreatedAt()),
                'edited_at'     => WikiApiSerializer::ms($comment->getEditedAt()),
                'deleted'       => $deleted,
                'content_view'  => $view,
                'body_markdown' => $deleted ? null : $this->content->markdown($principal, $page, $comment->getBody(), $view),
            ];
        }

        return [
            'id'          => $thread->getId(),
            'anchor'      => $thread->isPageLevel() ? null : 'inline',
            'quote'       => $this->service()->visibleQuote($thread),
            'resolved'    => $thread->isResolved(),
            'resolved_at' => WikiApiSerializer::ms($thread->getResolvedAt()),
            'resolved_by' => $people[count($comments)],
            'created_at'  => WikiApiSerializer::ms($thread->getCreatedAt()),
            'updated_at'  => WikiApiSerializer::ms($thread->getUpdatedAt()),
            'comments'    => $items,
        ];
    }

    /**
     * Runs a call of the comment service, mapping its refusals to the API's.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @throws WikiApiRefusal
     *
     * @return T
     */
    public function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (WikiCommentException $exception) {
            throw match ($exception->reason) {
                'forbidden'     => WikiApiRefusal::permissionRefused(),
                'not_found'     => WikiApiRefusal::notFound(),
                'too_large'     => new WikiApiRefusal(413, 'INPUT_003', 'Body too large'),
                'rate_limited'  => new WikiApiRefusal(429, 'RATE_003', 'Too many comments'),
                'limit_reached' => new WikiApiRefusal(422, 'COMMENT_001', 'A comment limit of this page is reached'),
                'stale'         => new WikiApiRefusal(409, 'COMMENT_002', 'The thread changed meanwhile'),
                default         => WikiApiRefusal::invalid('body'),
            };
        }
    }
}
