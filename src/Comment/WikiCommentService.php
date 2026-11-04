<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Comment;

use DateTimeInterface;
use Nowo\MilkdownEditorBundle\Comment\CommentAnchor;
use Nowo\MilkdownEditorBundle\Comment\InvalidCommentAnchorException;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiCommentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Search\WikiPlainText;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_filter;
use function array_key_exists;
use function array_key_last;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function is_bool;
use function is_string;
use function mb_strlen;
use function preg_match;
use function preg_replace;
use function preg_split;
use function spl_object_id;
use function str_contains;
use function strlen;
use function trim;

/**
 * The comments of a page (1.16, `comments.enabled`): the Milkdown 1.6 thread component's host endpoints (contract
 * 6.1 v1.2) as plain PHP. Every method takes the viewer, the space and a page the caller already found readable
 * (the 1.13.2 rule: 404 otherwise), then checks the comment rights itself — the server is the authority, the `can`
 * flags are hints:
 *
 * - {@see WikiAction::ViewComments} for everything (403 without it);
 * - {@see WikiAction::Comment} to start a thread, reply, react and refresh an anchor; with it an author edits and
 *   deletes their own comments;
 * - {@see WikiAction::ResolveComment} to resolve and reopen;
 * - {@see WikiAction::ModerateComments} to edit or delete any comment.
 *
 * Writes are rate limited per user and page (`comments.rate_limiter`), dispatch a {@see WikiCommentEvent} after the
 * flush and, with co-editing, publish the changed thread ids (never bodies) on the private topic
 * `<collab topic>/comments`. Bodies are Markdown (at most {@see self::MAX_BODY_CHARS} characters), rendered per viewer
 * with the page renderer and sanitized; author names follow {@see WikiAuthorLabel} (an opaque "Editor xxxx" unless
 * `history.show_names`), and pictures only beside real names. Stateless: safe in worker mode.
 *
 * @phpstan-type Rights array{view: bool, comment: bool, resolve: bool, moderate: bool}
 * @phpstan-type ThreadRef array{id: string, anchor: array<string, mixed>|null, resolved: bool, count: int, updatedAt: string}
 */
final readonly class WikiCommentService
{
    /** Longest comment body, in characters. */
    public const MAX_BODY_CHARS = 10000;

    /** Most threads listed per page (`truncated: true` beyond). */
    public const MAX_THREADS = 2000;

    /** Thread and comment ids (UUIDs; the component's id pattern). */
    public const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    /** Longest reaction emoji, in bytes. */
    public const MAX_EMOJI_BYTES = 16;

    /** Emoji accepted as reactions: pictographs with their modifiers, joiners and keycaps (at most {@see self::MAX_EMOJI_BYTES} bytes). */
    private const EMOJI = '/^(?:[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{3030}\x{303D}\x{3297}\x{3299}\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{E0020}-\x{E007F}][\x{FE0F}\x{200D}\x{20E3}\x{1F3FB}-\x{1F3FF}]*)+\z/u';

    /** Default caps (`comments.limits`): 422 `limit_reached` beyond them. */
    public const DEFAULT_LIMITS = [
        'threads_per_page'           => 500,
        'comments_per_thread'        => 500,
        'reactions_per_comment'      => 20,
        'user_reactions_per_comment' => 10,
    ];

    /** Most threads the server-rendered list shows (`truncated` beyond). */
    public const LIST_CAP = 50;

    /** Format of `updatedAt` / `resolvedAt` / `createdAt` (ISO 8601). */
    private const DATE = DateTimeInterface::ATOM;

    /**
     * @param WikiCollabService|null $collab co-editing (null: no live updates)
     * @param WikiCollabPublisherInterface|null $publisher the co-editing publisher (null: no live updates)
     * @param RateLimiterFactory|null $rateLimiter `comments.rate_limiter`, per user across all pages on writes
     * @param RateLimiterFactory|null $pageRateLimiter `comments.page_rate_limiter`, per user and page on writes (optional)
     * @param WikiAccessCheckerInterface|null $accessChecker the role gate (canEdit) of the full-anchor rule
     * @param bool $readViewSource `comments.read_view_source`: every commenter mounts the editor, so gets full anchors
     * @param array{threads_per_page: int, comments_per_thread: int, reactions_per_comment: int, user_reactions_per_comment: int} $limits `comments.limits`
     */
    public function __construct(
        private WikiCommentRepositoryInterface $comments,
        private WikiSpaceGuard $guard,
        private WikiAuthorLabel $authors,
        private WikiMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        private WikiMarkdownReferenceExtractor $references = new WikiMarkdownReferenceExtractor(),
        private ?EventDispatcherInterface $dispatcher = null,
        private ?WikiCollabService $collab = null,
        private ?WikiCollabPublisherInterface $publisher = null,
        private ?RateLimiterFactory $rateLimiter = null,
        private ?TranslatorInterface $translator = null,
        private ?RateLimiterFactory $pageRateLimiter = null,
        private ?WikiAccessCheckerInterface $accessChecker = null,
        private bool $readViewSource = false,
        private array $limits = self::DEFAULT_LIMITS,
    ) {
    }

    /**
     * CSRF token id of the comment writes of a page (header `X-CSRF-Token`, or `_token` of the no-JavaScript form).
     */
    public static function csrfTokenId(WikiPage $page): string
    {
        return 'wiki_comments_' . $page->getId();
    }

    /**
     * The viewer's comment rights on the page (hints for the markup; every write checks again).
     *
     * @return Rights
     */
    public function rights(UserInterface $user, WikiSpace $space, WikiPage $page): array
    {
        $view = $this->guard->isGranted($user, WikiAction::ViewComments, $space, $page);

        return [
            'view'     => $view,
            'comment'  => $view && $this->guard->isGranted($user, WikiAction::Comment, $space, $page),
            'resolve'  => $view && $this->guard->isGranted($user, WikiAction::ResolveComment, $space, $page),
            'moderate' => $view && $this->guard->isGranted($user, WikiAction::ModerateComments, $space, $page),
        ];
    }

    /**
     * The private Mercure topic of the page's comments (null without co-editing).
     */
    public function topic(WikiPage $page): ?string
    {
        return $this->collab instanceof WikiCollabService ? $this->collab->topic($page) . '/comments' : null;
    }

    /** Open (not resolved) threads of the page: the toggle's badge. */
    public function openCount(WikiPage $page): int
    {
        return $this->comments->countOpen($page);
    }

    /**
     * `GET /threads`: inline threads and page-level threads, as ThreadRefs.
     *
     * @return array{threads: list<ThreadRef>, page: list<ThreadRef>, truncated?: true}
     */
    public function list(UserInterface $user, WikiSpace $space, WikiPage $page): array
    {
        $this->require($this->rights($user, $space, $page)['view']);
        $full      = $this->fullAnchors($user, $page);
        $threads   = $this->comments->threads($page, self::MAX_THREADS + 1);
        $truncated = count($threads) > self::MAX_THREADS;
        $threads   = array_slice($threads, 0, self::MAX_THREADS);
        $counts    = $this->comments->commentCounts($threads);
        $answer    = ['threads' => [], 'page' => []];
        foreach ($threads as $thread) {
            $answer[$thread->isPageLevel() ? 'page' : 'threads'][] = $this->ref($thread, $counts[$thread->getId()] ?? 0, $full, $page);
        }
        if ($truncated) {
            $answer['truncated'] = true;
        }

        return $answer;
    }

    /**
     * `GET /threads/{id}`.
     *
     * @return array<string, mixed> the Thread JSON
     */
    public function thread(UserInterface $user, WikiSpace $space, WikiPage $page, string $id): array
    {
        $rights = $this->rights($user, $space, $page);
        $this->require($rights['view']);

        return $this->threadJson($user, $page, $this->requireThread($page, $id), $rights);
    }

    /**
     * The open threads rendered by the server in the panel: the page-level ones (the no-JavaScript list), or, with
     * `$withInline`, the inline ones too, each with its quote (1.16: the read view of viewers who do not get the page
     * source, where no editor resolves highlights).
     *
     * @param int $max at most this many threads (the oldest open ones)
     *
     * @return list<array<string, mixed>> Thread JSON objects
     */
    public function pageThreads(UserInterface $user, WikiSpace $space, WikiPage $page, bool $withInline = false, int $max = self::MAX_THREADS): array
    {
        $rights = $this->rights($user, $space, $page);
        $this->require($rights['view']);
        $threads = array_slice(array_values(array_filter($this->comments->threads($page, self::MAX_THREADS), static fn (WikiCommentThread $t): bool => !$t->isResolved() && ($withInline || $t->isPageLevel()))), 0, $max);

        return array_map(fn (WikiCommentThread $thread): array => $this->threadJson($user, $page, $thread, $rights), $threads);
    }

    /**
     * `POST /threads` `{anchor | null, body}`: starts an inline (validated anchor v1) or page-level thread.
     *
     * @return array<string, mixed> the new Thread JSON
     */
    public function create(UserInterface $user, WikiSpace $space, WikiPage $page, mixed $anchor, mixed $body): array
    {
        $rights = $this->rights($user, $space, $page);
        $this->require($rights['comment']);
        $text           = $this->body($body);
        [$json, $quote] = $anchor === null ? [null, null] : $this->anchor($anchor);
        $this->cap($this->comments->countThreads($page), 'threads_per_page');
        $this->limit($user, $page);

        $thread  = new WikiCommentThread($page, $user, $json, $quote);
        $comment = new WikiComment($thread, $user, $text);
        // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
        $this->comments->add($thread);
        // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
        $this->comments->add($comment);
        $this->comments->flush();
        $this->changed($page, $thread, $comment, $user, WikiEvents::COMMENT_CREATED, $text);

        return $this->threadJson($user, $page, $thread, $rights);
    }

    /**
     * `POST /threads/{id}/comments` `{body}`: a reply.
     *
     * @return array<string, mixed> the Thread JSON
     */
    public function reply(UserInterface $user, WikiSpace $space, WikiPage $page, string $id, mixed $body): array
    {
        $rights = $this->rights($user, $space, $page);
        $this->require($rights['comment']);
        $thread = $this->requireThread($page, $id);
        $text   = $this->body($body);
        $this->limit($user, $page);

        $existing = $this->comments->comments([$thread])[$thread->getId()] ?? [];
        $this->cap(count($existing), 'comments_per_thread');
        $last    = $existing === [] ? null : $existing[array_key_last($existing)];
        $comment = new WikiComment($thread, $user, $text, $last instanceof WikiComment ? $last->getPosition() + 1 : 0);
        $thread->touch();
        // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
        $this->comments->add($comment);
        $this->comments->flush();
        $this->changed($page, $thread, $comment, $user, WikiEvents::COMMENT_REPLIED, $text);

        return $this->threadJson($user, $page, $thread, $rights);
    }

    /**
     * `PATCH /threads/{id}`: `{resolved}` (ResolveComment) or `{anchor, base}` (Comment; 409 when `base` is not the
     * thread's `updatedAt`).
     *
     * @param array<string, mixed> $patch the decoded body
     *
     * @return array<string, mixed> the Thread JSON
     */
    public function patchThread(UserInterface $user, WikiSpace $space, WikiPage $page, string $id, array $patch): array
    {
        $rights = $this->rights($user, $space, $page);
        $this->require($rights['view']);
        $thread = $this->requireThread($page, $id);

        if (isset($patch['resolved'])) {
            if (!is_bool($patch['resolved']) || isset($patch['anchor'])) {
                throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid');
            }
            $this->require($rights['resolve']);
            $this->limit($user, $page);
            if ($patch['resolved'] !== $thread->isResolved()) {
                $patch['resolved'] ? $thread->resolve($user) : $thread->reopen();
                $this->comments->flush();
                $this->changed($page, $thread, null, $user, $patch['resolved'] ? WikiEvents::COMMENT_RESOLVED : WikiEvents::COMMENT_REOPENED);
            }

            return $this->threadJson($user, $page, $thread, $rights);
        }

        $this->require($rights['comment']);
        if ($thread->isPageLevel() || !isset($patch['anchor']) || !array_key_exists('base', $patch) || ($patch['base'] !== null && !is_string($patch['base']))) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid');
        }
        [$json, $quote] = $this->anchor($patch['anchor']);
        if ($patch['base'] !== $thread->getUpdatedAt()->format(self::DATE)) {
            throw new WikiCommentException(Response::HTTP_CONFLICT, 'stale');
        }
        $this->limit($user, $page);
        if ($json !== $thread->getAnchor()) {
            $thread->moveAnchor($json, $quote);
            $this->comments->flush();
            // An anchor refresh is not content: no event, but other viewers refetch the thread.
            $this->publish($page, $thread);
        }

        return $this->threadJson($user, $page, $thread, $rights);
    }

    /**
     * `PATCH /comments/{cid}` `{body}`: own comment with Comment, or any with ModerateComments.
     *
     * @return array<string, mixed> the Thread JSON
     */
    public function editComment(UserInterface $user, WikiSpace $space, WikiPage $page, string $cid, mixed $body): array
    {
        $rights  = $this->rights($user, $space, $page);
        $comment = $this->requireComment($page, $cid, $rights);
        $this->require(!$comment->isDeleted() && $this->mayChange($user, $comment, $rights));
        $text = $this->body($body);
        $this->limit($user, $page);

        $comment->edit($text);
        $comment->getThread()->touch();
        $this->comments->flush();
        $this->changed($page, $comment->getThread(), $comment, $user, WikiEvents::COMMENT_EDITED, $text);

        return $this->threadJson($user, $page, $comment->getThread(), $rights);
    }

    /**
     * `DELETE /comments/{cid}`: own comment with Comment, or any with ModerateComments. A comment that replies follow
     * becomes a "Comment deleted" placeholder; the last comment of a thread goes; a thread without any comment left
     * goes too (null: answer 204).
     *
     * @return array<string, mixed>|null the Thread JSON, or null when the thread is gone
     */
    public function deleteComment(UserInterface $user, WikiSpace $space, WikiPage $page, string $cid): ?array
    {
        $rights  = $this->rights($user, $space, $page);
        $comment = $this->requireComment($page, $cid, $rights);
        $this->require(!$comment->isDeleted() && $this->mayChange($user, $comment, $rights));
        $this->limit($user, $page);

        $thread = $comment->getThread();
        $all    = $this->comments->comments([$thread])[$thread->getId()] ?? [];
        $others = array_filter($all, static fn (WikiComment $c): bool => $c !== $comment && !$c->isDeleted());
        $later  = array_filter($all, static fn (WikiComment $c): bool => $c->getPosition() > $comment->getPosition());
        if ($others === []) {
            // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
            $this->comments->remove($thread);
            $this->comments->flush();
            $this->changed($page, $thread, null, $user, WikiEvents::COMMENT_DELETED);

            return null;
        }
        if ($later === []) {
            // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
            $this->comments->remove($comment);
        } else {
            $comment->markDeleted();
        }
        $thread->touch();
        $this->comments->flush();
        $this->changed($page, $thread, $comment, $user, WikiEvents::COMMENT_DELETED);

        return $this->threadJson($user, $page, $thread, $rights);
    }

    /**
     * `PUT` (`$on`) / `DELETE /comments/{cid}/reactions/{emoji}` (Comment).
     *
     * @return array<string, mixed> the Thread JSON
     */
    public function react(UserInterface $user, WikiSpace $space, WikiPage $page, string $cid, string $emoji, bool $on): array
    {
        $rights  = $this->rights($user, $space, $page);
        $comment = $this->requireComment($page, $cid, $rights);
        $this->require($rights['comment'] && !$comment->isDeleted());
        if (strlen($emoji) > self::MAX_EMOJI_BYTES || preg_match(self::EMOJI, $emoji) !== 1) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid');
        }
        $this->limit($user, $page);

        $existing = $this->comments->reaction($comment, $user, $emoji);
        if ($on && !$existing instanceof WikiCommentReaction) {
            $emojis = [];
            $mine   = 0;
            foreach ($this->comments->reactions([$comment]) as $reaction) {
                $emojis[$reaction->getEmoji()] = true;
                $mine += $this->same($reaction->getUser(), $user) ? 1 : 0;
            }
            if (!isset($emojis[$emoji])) {
                $this->cap(count($emojis), 'reactions_per_comment');
            }
            $this->cap($mine, 'user_reactions_per_comment');
            // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
            $this->comments->add(new WikiCommentReaction($comment, $user, $emoji));
        } elseif (!$on && $existing instanceof WikiCommentReaction) {
            // @igor-ignore - Doctrine unit of work of the comment store; the entity manager is reset by Doctrine between requests.
            $this->comments->remove($existing);
        }
        $comment->getThread()->touch();
        $this->comments->flush();
        $this->publish($page, $comment->getThread());

        return $this->threadJson($user, $page, $comment->getThread(), $rights);
    }

    /**
     * The body of a comment for a notification (e-mail, in-app text): sanitized HTML rendered **viewer-neutral**:
     * page links show *Content not visible* instead of a title and mentions stay `@handle`, because a
     * `nowo_wiki.comment.*` listener runs in the actor's request, whose rights are not the recipient's (audit EI-L2).
     * Check each recipient's {@see WikiAction::ViewComments} on the page before sending it; to show titles, render the
     * Markdown yourself in the recipient's own security context (for example a Messenger worker authenticated as them).
     */
    public function renderBodyForNotification(WikiComment $comment): string
    {
        return $comment->isDeleted() ? '' : $this->sanitizer->sanitize($this->renderer->render($comment->getBody()));
    }

    /**
     * Privacy export (1.16, audit EI-L6): every comment the user wrote, with its page, thread and Markdown body (no
     * rights check: call it from an administrative or data-subject-request flow).
     *
     * @return list<array{id: string, page_id: string, thread_id: string, body: string, created_at: string, edited_at: string|null, deleted: bool}>
     */
    public function exportAuthor(object $user): array
    {
        return array_map(static fn (WikiComment $comment): array => [
            'id'         => $comment->getId(),
            'page_id'    => $comment->getThread()->getPage()->getId(),
            'thread_id'  => $comment->getThread()->getId(),
            'body'       => $comment->getBody(),
            'created_at' => $comment->getCreatedAt()->format(self::DATE),
            'edited_at'  => $comment->getEditedAt()?->format(self::DATE),
            'deleted'    => $comment->isDeleted(),
        ], $this->comments->byAuthor($user));
    }

    /**
     * Privacy erasure (1.16, audit EI-L6): unlinks the user from their comments, threads and resolutions and deletes
     * their reactions; with `$deleteBodies` their comments also become "Comment deleted" placeholders (bodies blanked).
     * Quotes are page text, not the user's: they stay (edit the page or delete the thread to remove them).
     *
     * @return int the comments of the user that were changed
     */
    public function eraseAuthor(object $user, bool $deleteBodies = true): int
    {
        return $this->comments->eraseAuthor($user, $deleteBodies);
    }

    /**
     * The body of a comment as sanitized HTML for the current viewer (page links and mentions resolved for the user of
     * this request): the `html` of the Thread JSON. Never use it for someone else (see renderBodyForNotification()).
     */
    private function renderBody(WikiComment $comment): string
    {
        if ($comment->isDeleted()) {
            return '';
        }
        $markdown = $comment->getBody();
        $html     = $this->renderer instanceof WikiViewerMarkdownRendererInterface ? $this->renderer->renderForViewer($markdown) : $this->renderer->render($markdown);

        return $this->sanitizer->sanitize($html);
    }

    /**
     * @param Rights $rights
     *
     * @return array<string, mixed>
     */
    private function threadJson(UserInterface $user, WikiPage $page, WikiCommentThread $thread, array $rights): array
    {
        $full      = $this->fullAnchors($user, $page);
        $comments  = $this->comments->comments([$thread])[$thread->getId()] ?? [];
        $reactions = [];
        foreach ($this->comments->reactions($comments) as $reaction) {
            $entry = $reactions[$reaction->getComment()->getId()][$reaction->getEmoji()] ?? ['emoji' => $reaction->getEmoji(), 'count' => 0, 'mine' => false];
            ++$entry['count'];
            $entry['mine']                                                      = $entry['mine'] || $this->same($reaction->getUser(), $user);
            $reactions[$reaction->getComment()->getId()][$reaction->getEmoji()] = $entry;
        }

        // One avatar resolver call per response, for the people shown under their real name (WikiAuthorLabel).
        // A deleted comment shows no author, so its author is not presented (nor resolved) for it.
        $people    = array_values(array_filter([...array_map(static fn (WikiComment $c): ?object => $c->isDeleted() ? null : $c->getAuthor(), $comments), $thread->getResolvedBy()], static fn (?object $o): bool => $o !== null));
        $presented = $this->authors->presentAll($people, $page);
        $byObject  = [];
        foreach ($people as $index => $person) {
            $byObject[spl_object_id($person)] = $presented[$index];
        }

        $items = [];
        $count = 0;
        foreach ($comments as $comment) {
            $deleted = $comment->isDeleted();
            $count += $deleted ? 0 : 1;
            $author = $comment->getAuthor();
            $item   = [
                'id'        => $comment->getId(),
                'author'    => $author !== null && !$deleted ? $this->person($byObject[spl_object_id($author)]) : ['name' => $this->trans('wiki.comments.unknown_author'), 'avatarUrl' => null],
                'html'      => $this->renderBody($comment),
                'createdAt' => $comment->getCreatedAt()->format(self::DATE),
                'editedAt'  => $comment->getEditedAt()?->format(self::DATE),
                'reactions' => $deleted ? [] : array_values($reactions[$comment->getId()] ?? []),
                'can'       => [
                    'edit'   => !$deleted && $this->mayChange($user, $comment, $rights),
                    'delete' => !$deleted && $this->mayChange($user, $comment, $rights),
                ],
            ];
            if ($deleted) {
                $item['deleted'] = true;
            }
            $items[] = $item;
        }

        $resolvedBy = $thread->getResolvedBy();

        return $this->ref($thread, $count, $full, $page) + [
            'comments'   => $items,
            'can'        => ['reply' => $rights['comment'], 'resolve' => $rights['resolve'], 'react' => $rights['comment']],
            'resolvedBy' => $resolvedBy !== null ? $this->person($byObject[spl_object_id($resolvedBy)]) : ($thread->isResolved() ? ['name' => $this->trans('wiki.comments.unknown_author'), 'avatarUrl' => null] : null),
            'resolvedAt' => $thread->getResolvedAt()?->format(self::DATE),
        ];
    }

    /**
     * @param array{name: string, named: bool, avatarUrl: string|null} $presented
     *
     * @return array{name: string, avatarUrl: string|null}
     */
    private function person(array $presented): array
    {
        return ['name' => $presented['name'], 'avatarUrl' => $presented['avatarUrl']];
    }

    /**
     * @return ThreadRef
     */
    private function ref(WikiCommentThread $thread, int $count, bool $full, WikiPage $page): array
    {
        $anchor = $thread->getAnchor();
        if ($anchor !== null) {
            $anchor = CommentAnchor::fromJson($anchor)->toArray();
            // Viewers who do not mount the editor (audit EI-L7) get the quote only, and only while the page still has
            // that text: no context, offsets or heading, and nothing of text removed since (or never there).
            if (!$full) {
                $anchor = ['v' => 1, 'quote' => ['exact' => $this->stillInPage($anchor['quote']['exact'], $page) ? $anchor['quote']['exact'] : '']];
            }
        }

        return [
            'id'        => $thread->getId(),
            'anchor'    => $anchor,
            'resolved'  => $thread->isResolved(),
            'count'     => $count,
            'updatedAt' => $thread->getUpdatedAt()->format(self::DATE),
        ];
    }

    /**
     * @return array{0: string, 1: string} the normalised anchor JSON and its quote
     */
    private function anchor(mixed $anchor): array
    {
        try {
            $valid = CommentAnchor::fromArray($anchor);
        } catch (InvalidCommentAnchorException) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_anchor');
        }

        return [$valid->toJson(), $valid->exact];
    }

    private function body(mixed $body): string
    {
        if (!is_string($body) || trim($body) === '' || preg_match('//u', $body) !== 1 || str_contains($body, "\0")) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid');
        }
        if (mb_strlen($body, 'UTF-8') > self::MAX_BODY_CHARS) {
            throw new WikiCommentException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        }

        return trim($body);
    }

    private function requireThread(WikiPage $page, string $id): WikiCommentThread
    {
        $thread = preg_match(self::ID_PATTERN, $id) === 1 ? $this->comments->thread($page, $id) : null;
        if (!$thread instanceof WikiCommentThread) {
            throw new WikiCommentException(Response::HTTP_NOT_FOUND, 'not_found');
        }

        return $thread;
    }

    /**
     * @param Rights $rights
     */
    private function requireComment(WikiPage $page, string $cid, array $rights): WikiComment
    {
        $this->require($rights['view']);
        $comment = preg_match(self::ID_PATTERN, $cid) === 1 ? $this->comments->comment($page, $cid) : null;
        if (!$comment instanceof WikiComment) {
            throw new WikiCommentException(Response::HTTP_NOT_FOUND, 'not_found');
        }

        return $comment;
    }

    /**
     * Own comment with Comment, or any comment with ModerateComments.
     *
     * @param Rights $rights
     */
    private function mayChange(UserInterface $user, WikiComment $comment, array $rights): bool
    {
        return $rights['moderate'] || ($rights['comment'] && $this->same($comment->getAuthor(), $user));
    }

    private function same(?object $a, UserInterface $b): bool
    {
        return $a instanceof UserInterface && $a->getUserIdentifier() === $b->getUserIdentifier();
    }

    private function require(bool $granted): void
    {
        if (!$granted) {
            throw new WikiCommentException(Response::HTTP_FORBIDDEN, 'forbidden');
        }
    }

    private function limit(UserInterface $user, WikiPage $page): void
    {
        // Per user across every page (audit EI-L3), and optionally per user and page.
        if ($this->rateLimiter instanceof RateLimiterFactory
            && !$this->rateLimiter->create('nowo_wiki_comments:' . $user->getUserIdentifier())->consume()->isAccepted()) {
            throw new WikiCommentException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }
        if ($this->pageRateLimiter instanceof RateLimiterFactory
            && !$this->pageRateLimiter->create('nowo_wiki_comments_page:' . $user->getUserIdentifier() . '|' . $page->getId())->consume()->isAccepted()) {
            throw new WikiCommentException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }
    }

    private function changed(WikiPage $page, WikiCommentThread $thread, ?WikiComment $comment, UserInterface $user, string $event, ?string $body = null): void
    {
        $this->publish($page, $thread);
        $this->dispatcher?->dispatch(new WikiCommentEvent($page, $thread, $comment, $user, $body === null ? [] : $this->references->mentions($body)), $event);
    }

    /** Live update: the changed thread id on the page's private comments topic (ids only, never bodies). */
    private function publish(WikiPage $page, WikiCommentThread $thread): void
    {
        $topic = $this->topic($page);
        if ($topic === null || !$this->publisher instanceof WikiCollabPublisherInterface) {
            return;
        }
        $this->publisher->publish($topic, '{"threads":["' . $thread->getId() . '"]}');
    }

    /**
     * Whether the viewer gets full anchors: they mount the editor (they may edit the page, or
     * `comments.read_view_source` mounts it for every commenter).
     */
    private function fullAnchors(UserInterface $user, WikiPage $page): bool
    {
        return $this->readViewSource
            || ($this->guard->isGranted($user, WikiAction::Edit, $page->getSpace(), $page) && ($this->accessChecker?->canEdit($user) ?? true));
    }

    /**
     * Whether every piece of a quote (atoms are U+FFFC in anchors, so they split it) still occurs in the plain text of
     * the page's current revision.
     */
    private function stillInPage(string $quote, WikiPage $page): bool
    {
        $revision = $page->getCurrentRevision();
        $text     = $revision instanceof WikiPageRevision ? $revision->getContentText() ?? WikiPlainText::fromHtml($revision->getContentHtml()) : ('');
        foreach (preg_split('/\x{FFFC}/u', $quote) ?: [] as $piece) {
            $piece = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $piece));
            if ($piece !== '' && !str_contains($text, $piece)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param 'comments_per_thread'|'reactions_per_comment'|'threads_per_page'|'user_reactions_per_comment' $limit
     */
    private function cap(int $current, string $limit): void
    {
        if ($current >= $this->limits[$limit]) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'limit_reached');
        }
    }

    private function trans(string $key): string
    {
        return $this->translator?->trans($key, [], 'NowoWikiBundle') ?? $key;
    }
}
