<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Comment;

use Doctrine\ORM\EntityManager;
use Nowo\MilkdownEditorBundle\Comment\CommentAnchor;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiCommentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use Nowo\WikiBundle\Tests\Support\RecordingAvatarResolver;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_column;
use function array_slice;
use function count;
use function in_array;
use function str_repeat;
use function strlen;

/**
 * 1.16: the comment endpoints' logic — default-deny rights, own comments, moderation, anchors (validated, 409 when
 * stale), deletion rules, reactions, events, live updates (ids only) and the author / avatar policy.
 */
final class WikiCommentServiceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    private TestUserEntity $ana;

    private TestUserEntity $bob;

    /** @var array<string, list<WikiAction>> user id => granted actions */
    private array $grants = [];

    /** @var list<array{0: string, 1: WikiCommentEvent}> */
    private array $events = [];

    /** @var list<array{0: string, 1: string}> */
    private array $published = [];

    private RecordingAvatarResolver $avatars;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'run', 'Runbooks');
        $this->ana   = (new TestUserEntity('id-ana'))->withDisplayName('Ana Garcia');
        $this->bob   = (new TestUserEntity('id-bob'))->withDisplayName('Bob Stone');
        foreach ([$this->space, $this->page, $this->ana, $this->bob] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        // Ana edits the page (full anchors); Bob only comments.
        $all          = [WikiAction::Edit, ...WikiAction::commentActions()];
        $this->grants = ['id-ana' => $all, 'id-bob' => [WikiAction::ViewComments, WikiAction::Comment]];
    }

    public function testDefaultDenyWithoutViewComments(): void
    {
        $this->grants = ['id-ana' => [WikiAction::Comment, WikiAction::ResolveComment, WikiAction::ModerateComments]];
        $service      = $this->service();

        self::assertSame(['view' => false, 'comment' => false, 'resolve' => false, 'moderate' => false], $service->rights($this->ana, $this->space, $this->page));
        foreach ([
            fn (): array => $service->list($this->ana, $this->space, $this->page),
            fn (): array => $service->thread($this->ana, $this->space, $this->page, 'x'),
            fn (): array => $service->pageThreads($this->ana, $this->space, $this->page),
            fn (): array => $service->create($this->ana, $this->space, $this->page, null, 'Hi'),
            fn (): array => $service->patchThread($this->ana, $this->space, $this->page, 'x', ['resolved' => true]),
            fn (): array => $service->editComment($this->ana, $this->space, $this->page, 'x', 'Hi'),
            fn (): ?array => $service->deleteComment($this->ana, $this->space, $this->page, 'x'),
            fn (): array => $service->react($this->ana, $this->space, $this->page, 'x', '👍', true),
        ] as $call) {
            $this->assertRefused(403, 'forbidden', $call);
        }
    }

    public function testCreatesListsAndRendersThreads(): void
    {
        $service = $this->service();
        $inline  = $service->create($this->ana, $this->space, $this->page, $this->anchor(), 'Is **this** still true?');
        $page    = $service->create($this->ana, $this->space, $this->page, null, 'Page-level note');

        self::assertSame(36, strlen($inline['id']));
        self::assertSame('deploy', $inline['anchor']['quote']['exact']);
        self::assertNull($inline['anchor']['yjs']);
        self::assertFalse($inline['resolved']);
        self::assertSame(1, $inline['count']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $inline['updatedAt']);
        self::assertSame(['reply' => true, 'resolve' => true, 'react' => true], $inline['can']);
        self::assertNull($inline['resolvedBy']);
        self::assertNull($inline['resolvedAt']);
        $comment = $inline['comments'][0];
        self::assertSame('<p>Is <strong>this</strong> still true?</p>', $comment['html']);
        self::assertSame(['name' => 'Ana Garcia', 'avatarUrl' => '/files/ana/s64'], $comment['author']);
        self::assertSame(['edit' => true, 'delete' => true], $comment['can']);
        self::assertSame([], $comment['reactions']);
        self::assertNull($comment['editedAt']);
        self::assertArrayNotHasKey('deleted', $comment);

        $list = $service->list($this->ana, $this->space, $this->page);
        self::assertSame([$inline['id']], array_column($list['threads'], 'id'));
        self::assertSame([$page['id']], array_column($list['page'], 'id'));
        self::assertNull($list['page'][0]['anchor']);
        self::assertArrayNotHasKey('truncated', $list);
        self::assertSame(2, $service->openCount($this->page));
        self::assertSame($inline, $service->thread($this->ana, $this->space, $this->page, $inline['id']));
        self::assertSame([$page['id']], array_column($service->pageThreads($this->ana, $this->space, $this->page), 'id'));
        // The server-rendered read view lists the inline threads too (with their anchor, hence their quote).
        self::assertEqualsCanonicalizing([$inline['id'], $page['id']], array_column($service->pageThreads($this->ana, $this->space, $this->page, true), 'id'));

        self::assertSame([WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_CREATED], array_column($this->events, 0));
        self::assertSame($this->page, $this->events[0][1]->page);
        self::assertSame($this->ana, $this->events[0][1]->actor);
        self::assertInstanceOf(WikiComment::class, $this->events[0][1]->comment);
        self::assertSame([['eng-topic/page-' . $this->page->getId() . '/comments', '{"threads":["' . $inline['id'] . '"]}']], [$this->published[0]]);
    }

    public function testValidatesBodiesAnchorsAndIds(): void
    {
        $service = $this->service();
        $this->assertRefused(422, 'invalid', fn (): array => $service->create($this->ana, $this->space, $this->page, null, '   '));
        $this->assertRefused(422, 'invalid', fn (): array => $service->create($this->ana, $this->space, $this->page, null, ['x']));
        $this->assertRefused(422, 'invalid', fn (): array => $service->create($this->ana, $this->space, $this->page, null, "a\0b"));
        $this->assertRefused(422, 'invalid', fn (): array => $service->create($this->ana, $this->space, $this->page, null, "\xC3\x28"));
        $this->assertRefused(413, 'too_large', fn (): array => $service->create($this->ana, $this->space, $this->page, null, str_repeat('é', WikiCommentService::MAX_BODY_CHARS + 1)));
        $this->assertRefused(422, 'invalid_anchor', fn (): array => $service->create($this->ana, $this->space, $this->page, ['v' => 2], 'Hi'));
        $this->assertRefused(404, 'not_found', fn (): array => $service->thread($this->ana, $this->space, $this->page, 'bad id!'));
        $this->assertRefused(404, 'not_found', fn (): array => $service->reply($this->ana, $this->space, $this->page, 'missing', 'Hi'));
        $this->assertRefused(404, 'not_found', fn (): array => $service->editComment($this->ana, $this->space, $this->page, 'bad id!', 'Hi'));
        $this->assertRefused(404, 'not_found', fn (): ?array => $service->deleteComment($this->ana, $this->space, $this->page, 'missing'));

        // A thread of another page is not found through this page.
        $other = new WikiPage($this->space, 'other', 'Other');
        $this->em->persist($other);
        $this->em->flush();
        $thread = $service->create($this->ana, $this->space, $other, null, 'Elsewhere');
        $this->assertRefused(404, 'not_found', fn (): array => $service->thread($this->ana, $this->space, $this->page, $thread['id']));
        $this->assertRefused(404, 'not_found', fn (): array => $service->editComment($this->ana, $this->space, $this->page, $thread['comments'][0]['id'], 'x'));
    }

    public function testRepliesEditsAndOwnership(): void
    {
        $service = $this->service();
        $thread  = $service->create($this->bob, $this->space, $this->page, null, 'From Bob');
        $replied = $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Bob again');
        self::assertSame(2, $replied['count']);
        self::assertSame(['From Bob', 'Bob again'], array_map(static fn (array $c): string => strip_tags(trim($c['html'])), $replied['comments']));
        self::assertGreaterThan($thread['updatedAt'], $replied['updatedAt']);
        self::assertSame(['reply' => true, 'resolve' => false, 'react' => true], $replied['can']);

        // Bob edits his own comment; he may not resolve; Ana (moderator) may edit Bob's comment.
        $edited = $service->editComment($this->bob, $this->space, $this->page, $replied['comments'][0]['id'], 'Edited by Bob');
        self::assertNotNull($edited['comments'][0]['editedAt']);
        $this->assertRefused(403, 'forbidden', fn (): array => $service->patchThread($this->bob, $this->space, $this->page, $thread['id'], ['resolved' => true]));
        $ana = $service->create($this->ana, $this->space, $this->page, null, 'From Ana');
        $this->assertRefused(403, 'forbidden', fn (): array => $service->editComment($this->bob, $this->space, $this->page, $ana['comments'][0]['id'], 'Hijack'));
        $this->assertRefused(403, 'forbidden', fn (): ?array => $service->deleteComment($this->bob, $this->space, $this->page, $ana['comments'][0]['id']));
        $bobView = $service->thread($this->bob, $this->space, $this->page, $ana['id']);
        self::assertSame(['edit' => false, 'delete' => false], $bobView['comments'][0]['can']);
        $moderated = $service->editComment($this->ana, $this->space, $this->page, $replied['comments'][1]['id'], 'Moderated');
        self::assertStringContainsString('Moderated', $moderated['comments'][1]['html']);

        $this->assertRefused(422, 'invalid', fn (): array => $service->editComment($this->ana, $this->space, $this->page, $replied['comments'][1]['id'], ''));
        self::assertSame([WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_REPLIED, WikiEvents::COMMENT_EDITED, WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_EDITED], array_column($this->events, 0));
    }

    public function testResolveReopenAndAnchorRefresh(): void
    {
        $service = $this->service();
        $thread  = $service->create($this->ana, $this->space, $this->page, $this->anchor(), 'Check this');

        $resolved = $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => true]);
        self::assertTrue($resolved['resolved']);
        self::assertSame(['name' => 'Ana Garcia', 'avatarUrl' => '/files/ana/s64'], $resolved['resolvedBy']);
        self::assertNotNull($resolved['resolvedAt']);
        self::assertSame(0, $service->openCount($this->page));
        // Resolving again changes nothing (no second event).
        $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => true]);
        $reopened = $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => false]);
        self::assertFalse($reopened['resolved']);
        self::assertNull($reopened['resolvedBy']);
        self::assertSame([WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_RESOLVED, WikiEvents::COMMENT_REOPENED], array_column($this->events, 0));
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => 'yes']));
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => true, 'anchor' => $this->anchor()]));

        // Anchor refresh: needs the current updatedAt as base (409 otherwise), a valid anchor, an inline thread.
        $moved = $this->anchor('deploy', 10);
        $this->assertRefused(409, 'stale', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => '2000-01-01T00:00:00+00:00']));
        $this->assertRefused(409, 'stale', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => null]));
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved]));
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => 5]));
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], []));
        $this->assertRefused(422, 'invalid_anchor', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => ['v' => 1], 'base' => $reopened['updatedAt']]));
        $published = count($this->published);
        $refreshed = $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => $reopened['updatedAt']]);
        self::assertSame(10, $refreshed['anchor']['position']['start']);
        self::assertCount($published + 1, $this->published);
        // The same anchor again: nothing written, nothing published.
        $same = $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => $refreshed['updatedAt']]);
        self::assertSame($refreshed['updatedAt'], $same['updatedAt']);
        self::assertCount($published + 1, $this->published);
        $stored = $this->em->find(WikiCommentThread::class, $thread['id']);
        self::assertSame('deploy', $stored?->getQuote());

        $pageLevel = $service->create($this->ana, $this->space, $this->page, null, 'Page');
        $this->assertRefused(422, 'invalid', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $pageLevel['id'], ['anchor' => $moved, 'base' => $pageLevel['updatedAt']]));
        $this->grants['id-ana'] = [WikiAction::ViewComments, WikiAction::ResolveComment];
        $this->assertRefused(403, 'forbidden', fn (): array => $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['anchor' => $moved, 'base' => $same['updatedAt']]));
    }

    public function testDeletionKeepsPlaceholdersAndDropsEmptyThreads(): void
    {
        $service                  = $this->service();
        $thread                   = $service->create($this->ana, $this->space, $this->page, null, 'First');
        $thread                   = $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Second');
        $thread                   = $service->reply($this->ana, $this->space, $this->page, $thread['id'], 'Third');
        [$first, $second, $third] = array_column($thread['comments'], 'id');

        // A comment that replies follow becomes a placeholder.
        $after = $service->deleteComment($this->ana, $this->space, $this->page, $first);
        self::assertNotNull($after);
        self::assertTrue($after['comments'][0]['deleted']);
        self::assertSame('', $after['comments'][0]['html']);
        self::assertSame(['name' => 'Deleted user (t)', 'avatarUrl' => null], $after['comments'][0]['author']);
        self::assertSame(['edit' => false, 'delete' => false], $after['comments'][0]['can']);
        self::assertSame([], $after['comments'][0]['reactions']);
        self::assertSame(2, $after['count']);
        $this->assertRefused(403, 'forbidden', fn (): ?array => $service->deleteComment($this->ana, $this->space, $this->page, $first));
        $this->assertRefused(403, 'forbidden', fn (): array => $service->react($this->ana, $this->space, $this->page, $first, '👍', true));

        // The last comment goes for good.
        $after = $service->deleteComment($this->ana, $this->space, $this->page, $third);
        self::assertNotNull($after);
        self::assertSame([$first, $second], array_column($after['comments'], 'id'));
        self::assertNull($this->em->find(WikiComment::class, $third));

        // No comment left: the thread goes (204).
        self::assertNull($service->deleteComment($this->ana, $this->space, $this->page, $second));
        self::assertNull($this->em->find(WikiCommentThread::class, $thread['id']));
        self::assertNull($this->em->find(WikiComment::class, $first));
        self::assertSame(array_fill(0, 3, WikiEvents::COMMENT_DELETED), array_slice(array_column($this->events, 0), -3));
        self::assertNull($this->events[count($this->events) - 1][1]->comment);
    }

    public function testReactions(): void
    {
        $service = $this->service();
        $thread  = $service->create($this->ana, $this->space, $this->page, null, 'React to me');
        $cid     = $thread['comments'][0]['id'];

        $service->react($this->ana, $this->space, $this->page, $cid, '👍', true);
        $service->react($this->ana, $this->space, $this->page, $cid, '👍', true);
        $both = $service->react($this->bob, $this->space, $this->page, $cid, '👍', true);
        self::assertSame([['emoji' => '👍', 'count' => 2, 'mine' => true]], $both['comments'][0]['reactions']);
        $service->react($this->bob, $this->space, $this->page, $cid, '👍🏽', true);
        $off = $service->react($this->bob, $this->space, $this->page, $cid, '👍', false);
        self::assertSame([['emoji' => '👍', 'count' => 1, 'mine' => false], ['emoji' => '👍🏽', 'count' => 1, 'mine' => true]], $off['comments'][0]['reactions']);
        $service->react($this->bob, $this->space, $this->page, $cid, '👍', false);
        foreach (['x', '<b>', str_repeat('👍', 5), ''] as $bad) {
            $this->assertRefused(422, 'invalid', fn (): array => $service->react($this->bob, $this->space, $this->page, $cid, $bad, true));
        }
        $this->grants['id-bob'] = [WikiAction::ViewComments];
        $this->assertRefused(403, 'forbidden', fn (): array => $service->react($this->bob, $this->space, $this->page, $cid, '👍', true));

        // A comment with reactions is deleted with them.
        self::assertNull($service->deleteComment($this->ana, $this->space, $this->page, $cid));
    }

    public function testRateLimitsWrites(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'c', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service(limiter: $limiter);
        $service->create($this->ana, $this->space, $this->page, null, 'One');

        $this->assertRefused(429, 'rate_limited', fn (): array => $service->create($this->ana, $this->space, $this->page, null, 'Two'));
        // Reads are not limited.
        self::assertCount(1, $service->list($this->ana, $this->space, $this->page)['page']);
    }

    public function testAuthorNamesFollowTheHistoryPolicy(): void
    {
        $service = $this->service(showNames: false);
        $thread  = $service->create($this->ana, $this->space, $this->page, null, 'Opaque');

        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $thread['comments'][0]['author']['name']);
        self::assertNull($thread['comments'][0]['author']['avatarUrl']);
        self::assertSame([], $this->avatars->calls, 'Hidden names: the avatar resolver is never called.');

        $named = $this->service();
        $named->reply($this->bob, $this->space, $this->page, $thread['id'], 'Named');
        self::assertSame([['id-ana', 'id-bob']], $this->avatars->calls, 'One resolver call per response.');
    }

    public function testListsAreCappedAndTruncated(): void
    {
        $service = $this->service();
        $page    = $this->page;
        for ($i = 0; $i <= WikiCommentService::MAX_THREADS; ++$i) {
            $this->em->persist(new WikiCommentThread($page, $this->ana));
        }
        $this->em->flush();

        $list = $service->list($this->ana, $this->space, $page);
        self::assertTrue($list['truncated']);
        self::assertCount(WikiCommentService::MAX_THREADS, $list['page']);
        self::assertSame(0, $list['page'][0]['count']);
    }

    public function testRendersForTheViewerAndWorksWithoutCollaboration(): void
    {
        $renderer = new class implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            public function render(string $markdown): string
            {
                return '<p>neutral</p>';
            }

            public function renderForViewer(string $markdown): string
            {
                return '<p>for viewer <script>x</script></p>';
            }
        };
        $service = new WikiCommentService(
            new DoctrineOrmWikiCommentRepository($this->em),
            $this->guard(),
            new WikiAuthorLabel('secret', true),
            $renderer,
            new WikiHtmlSanitizer(),
        );
        $thread = $service->create($this->ana, $this->space, $this->page, null, 'Body');

        self::assertSame('<p>for viewer </p>', $thread['comments'][0]['html']);
        self::assertNull($service->topic($this->page));
        self::assertSame('wiki_comments_' . $this->page->getId(), WikiCommentService::csrfTokenId($this->page));
        // A deleted user's comment and resolve: an untranslated label without a translator.
        $stored = $this->em->find(WikiCommentThread::class, $thread['id']);
        self::assertNotNull($stored);
        $stored->resolve($this->ana);
        (new ReflectionProperty(WikiCommentThread::class, 'resolvedBy'))->setValue($stored, null);
        $comment = $this->em->find(WikiComment::class, $thread['comments'][0]['id']);
        (new ReflectionProperty(WikiComment::class, 'author'))->setValue($comment, null);
        $json = $service->thread($this->ana, $this->space, $this->page, $thread['id']);
        self::assertSame('wiki.comments.unknown_author', $json['comments'][0]['author']['name']);
        self::assertSame(['name' => 'wiki.comments.unknown_author', 'avatarUrl' => null], $json['resolvedBy']);
        self::assertSame(['edit' => true, 'delete' => true], $json['comments'][0]['can'], 'A moderator may still change it.');
    }

    public function testNonEditorsGetOnlyQuotesStillInThePage(): void
    {
        // Audit EI-L7: a secret removed from the page must not stay readable through the thread JSON.
        $this->revise('<p>The prod key is AKIA-SECRET-KEY-123 and the region is eu-west.</p>');
        $service = $this->service();
        $secret  = $service->create($this->ana, $this->space, $this->page, $this->anchor('AKIA-SECRET-KEY-123', 15), 'Remove this!');
        $region  = $service->create($this->ana, $this->space, $this->page, $this->anchor('eu-west', 54), 'OK');
        $atom    = $service->create($this->ana, $this->space, $this->page, $this->anchor("prod\u{FFFC}key", 4), 'Atom');

        // Bob (no Edit) while the text is there: the quote only, no context, offsets or heading.
        self::assertSame(['v' => 1, 'quote' => ['exact' => 'AKIA-SECRET-KEY-123']], $service->thread($this->bob, $this->space, $this->page, $secret['id'])['anchor']);
        self::assertSame(['v' => 1, 'quote' => ['exact' => "prod\u{FFFC}key"]], $service->thread($this->bob, $this->space, $this->page, $atom['id'])['anchor']);

        $this->revise('<p>The prod key lives in the vault and the region is eu-west.</p>');
        $service->patchThread($this->ana, $this->space, $this->page, $secret['id'], ['resolved' => true]);
        $listed = array_column($service->list($this->bob, $this->space, $this->page)['threads'], 'anchor', 'id');
        foreach ([$service->thread($this->bob, $this->space, $this->page, $secret['id'])['anchor'], $listed[$secret['id']]] as $anchor) {
            self::assertSame(['v' => 1, 'quote' => ['exact' => '']], $anchor);
        }
        self::assertSame('eu-west', $service->thread($this->bob, $this->space, $this->page, $region['id'])['anchor']['quote']['exact']);
        self::assertStringNotContainsString('AKIA', (string) json_encode($service->pageThreads($this->bob, $this->space, $this->page, true)));
        // Editors keep the full anchor (the editor resolves and refreshes it).
        $full = $service->thread($this->ana, $this->space, $this->page, $secret['id'])['anchor'];
        self::assertSame(['exact' => 'AKIA-SECRET-KEY-123', 'prefix' => 'We ', 'suffix' => ' daily'], $full['quote']);
        self::assertSame(15, $full['position']['start']);
        // A page without a revision has no text: every quote is blank for non-editors.
        $this->page->setCurrentRevision(null);
        self::assertSame('', $service->thread($this->bob, $this->space, $this->page, $region['id'])['anchor']['quote']['exact']);
    }

    public function testCapsThreadsCommentsAndReactions(): void
    {
        $service = $this->service(limits: ['threads_per_page' => 2, 'user_threads_per_page' => 50, 'comments_per_thread' => 2, 'reactions_per_comment' => 2, 'user_reactions_per_comment' => 2]);
        $thread  = $service->create($this->ana, $this->space, $this->page, null, 'One');
        $service->create($this->ana, $this->space, $this->page, null, 'Two');
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->create($this->ana, $this->space, $this->page, null, 'Three'));

        $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Reply');
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Again'));

        $cid = $thread['comments'][0]['id'];
        $service->react($this->ana, $this->space, $this->page, $cid, '👍', true);
        $service->react($this->ana, $this->space, $this->page, $cid, '🎉', true);
        // The same emoji again is fine; a third distinct one, or a third of one user's, is not.
        $service->react($this->bob, $this->space, $this->page, $cid, '👍', true);
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->react($this->bob, $this->space, $this->page, $cid, '🚀', true));
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->react($this->ana, $this->space, $this->page, $cid, '🚀', true));
        $service->react($this->bob, $this->space, $this->page, $cid, '🎉', true);
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->react($this->bob, $this->space, $this->page, $cid, '👀', true));
    }

    public function testTheRateLimiterIsPerUserAcrossPagesPlusAnOptionalPageBucket(): void
    {
        // Audit EI-L3: limit 2 per user, whatever the page.
        $other = new WikiPage($this->space, 'other', 'Other');
        $this->em->persist($other);
        $this->em->flush();
        $user    = new RateLimiterFactory(['id' => 'u', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service(limiter: $user);
        $service->create($this->ana, $this->space, $this->page, null, 'p1');
        $service->create($this->ana, $this->space, $other, null, 'p2');
        $this->assertRefused(429, 'rate_limited', fn (): array => $service->create($this->ana, $this->space, $other, null, 'p2 again'));
        $service->create($this->bob, $this->space, $other, null, 'Bob has his own bucket');

        $page    = new RateLimiterFactory(['id' => 'p', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service(pageLimiter: $page);
        $service->create($this->ana, $this->space, $this->page, null, 'p1');
        $this->assertRefused(429, 'rate_limited', fn (): array => $service->create($this->ana, $this->space, $this->page, null, 'p1 again'));
        $service->create($this->ana, $this->space, $other, null, 'another page');
    }

    public function testNotificationBodiesAreViewerNeutralAndPrivacyRequests(): void
    {
        $renderer = new class implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            public function render(string $markdown): string
            {
                return '<p>neutral: Content not visible</p>';
            }

            public function renderForViewer(string $markdown): string
            {
                return '<p>Secret page title</p>';
            }
        };
        $service = new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), $this->guard(), new WikiAuthorLabel('secret'), $renderer, new WikiHtmlSanitizer());
        $thread  = $service->create($this->bob, $this->space, $this->page, null, 'See [[wiki:x]]');
        $comment = $this->em->find(WikiComment::class, $thread['comments'][0]['id']);
        self::assertInstanceOf(WikiComment::class, $comment);
        // Audit EI-L2: the actor's view never reaches a notification.
        self::assertSame('<p>Secret page title</p>', $thread['comments'][0]['html']);
        self::assertSame('<p>neutral: Content not visible</p>', $service->renderBodyForNotification($comment));

        // Audit EI-L6: export, then erase (bodies blanked, reactions gone, threads unlinked).
        $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Second');
        $service->react($this->bob, $this->space, $this->page, $comment->getId(), '👍', true);
        $service->patchThread($this->ana, $this->space, $this->page, $thread['id'], ['resolved' => true]);
        $export = $service->exportAuthor($this->bob);
        self::assertSame(['See [[wiki:x]]', 'Second'], array_column($export, 'body'));
        self::assertSame($this->page->getId(), $export[0]['page_id']);
        self::assertFalse($export[0]['deleted']);
        self::assertSame(2, $service->eraseAuthor($this->bob));
        self::assertSame('', $service->renderBodyForNotification($comment));
        $this->em->clear();
        $after = $service->thread($this->ana, $this->space, $this->page, $thread['id']);
        self::assertSame([true, true], array_column($after['comments'], 'deleted'));
        self::assertNull($this->em->find(WikiCommentThread::class, $thread['id'])?->getCreatedBy());
        self::assertSame([], $service->exportAuthor($this->em->find(TestUserEntity::class, 'id-ana') ?? $this->ana));
        // Ana keeps her (no) comments but loses the resolution.
        self::assertSame(0, $service->eraseAuthor($this->em->find(TestUserEntity::class, 'id-ana') ?? $this->ana, false));
        $this->em->clear();
        self::assertNull($this->em->find(WikiCommentThread::class, $thread['id'])?->getResolvedBy());
    }

    public function testReactionsAreGoneAfterAnErasure(): void
    {
        $service = $this->service();
        $thread  = $service->create($this->ana, $this->space, $this->page, null, 'React');
        $service->react($this->bob, $this->space, $this->page, $thread['comments'][0]['id'], '👍', true);
        $service->eraseAuthor($this->bob, false);
        $this->em->clear();

        self::assertSame([], $service->thread($this->ana, $this->space, $this->page, $thread['id'])['comments'][0]['reactions']);
    }

    public function testResolvedThreadsDoNotCountAndEachUserHasAShare(): void
    {
        // Audit EI-I16: resolved threads never lock a page; one user may only fill their own share.
        $service = $this->service(limits: ['threads_per_page' => 3, 'user_threads_per_page' => 2] + WikiCommentService::DEFAULT_LIMITS);
        $one     = $service->create($this->bob, $this->space, $this->page, null, 'One');
        $service->create($this->bob, $this->space, $this->page, null, 'Two');
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->create($this->bob, $this->space, $this->page, null, 'Three'));
        $service->create($this->ana, $this->space, $this->page, null, 'Ana');
        $this->assertRefused(422, 'limit_reached', fn (): array => $service->create($this->ana, $this->space, $this->page, null, 'Page full'));
        $service->patchThread($this->ana, $this->space, $this->page, $one['id'], ['resolved' => true]);
        $service->create($this->bob, $this->space, $this->page, null, 'Room again');
    }

    public function testModeratorsDeleteAWholeThread(): void
    {
        $service = $this->service();
        $thread  = $service->create($this->bob, $this->space, $this->page, null, 'Spam');
        $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'More spam');
        $service->react($this->bob, $this->space, $this->page, $thread['comments'][0]['id'], '👍', true);

        $this->assertRefused(403, 'forbidden', fn () => $service->deleteThread($this->bob, $this->space, $this->page, $thread['id']));
        $this->assertRefused(404, 'not_found', fn () => $service->deleteThread($this->ana, $this->space, $this->page, 'missing'));
        $service->deleteThread($this->ana, $this->space, $this->page, $thread['id']);
        $this->em->clear();

        self::assertNull($this->em->find(WikiCommentThread::class, $thread['id']));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_comments'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_comment_reactions'));
        $last = $this->events[count($this->events) - 1];
        self::assertSame(WikiEvents::COMMENT_THREAD_DELETED, $last[0]);
        self::assertNull($last[1]->comment);
        self::assertSame($this->ana, $last[1]->actor);
    }

    public function testTheServerListShowsTheLatestCommentsOfAThread(): void
    {
        // Audit EI-I18: at most LIST_COMMENTS_CAP rendered comments per listed thread.
        $service = $this->service();
        $thread  = $service->create($this->ana, $this->space, $this->page, null, 'First');
        for ($i = 1; $i < WikiCommentService::LIST_COMMENTS_CAP + 3; ++$i) {
            $service->reply($this->bob, $this->space, $this->page, $thread['id'], 'Reply ' . $i);
        }
        $listed = $service->pageThreads($this->bob, $this->space, $this->page)[0];

        self::assertSame(3, $listed['more']);
        self::assertCount(WikiCommentService::LIST_COMMENTS_CAP, $listed['comments']);
        self::assertStringContainsString('Reply ' . (WikiCommentService::LIST_COMMENTS_CAP + 2), (string) end($listed['comments'])['html']);
        self::assertSame(WikiCommentService::LIST_COMMENTS_CAP + 3, $listed['count']);
        self::assertArrayNotHasKey('more', $service->thread($this->bob, $this->space, $this->page, $thread['id']));
    }

    public function testVisibleQuoteExportDataAndTheErasureEvent(): void
    {
        // Audit EI-I15: the quote a notification may show.
        $this->revise('<p>The prod key is AKIA-SECRET-KEY-123.</p>');
        $service = $this->service();
        $inline  = $service->create($this->bob, $this->space, $this->page, $this->anchor('AKIA-SECRET-KEY-123', 15), 'Secret');
        $page    = $service->create($this->bob, $this->space, $this->page, null, 'Page');
        $thread  = $this->em->find(WikiCommentThread::class, $inline['id']);
        self::assertInstanceOf(WikiCommentThread::class, $thread);
        self::assertSame('AKIA-SECRET-KEY-123', $service->visibleQuote($thread));
        $this->revise('<p>The prod key lives in the vault.</p>');
        self::assertSame('', $service->visibleQuote($thread));
        self::assertSame('AKIA-SECRET-KEY-123', $thread->getQuote());
        self::assertNull($service->visibleQuote($this->em->find(WikiCommentThread::class, $page['id']) ?? $thread));

        // Audit EI-I17: the export holds reactions and threads started or resolved; the erasure is announced.
        $service->react($this->bob, $this->space, $this->page, $inline['comments'][0]['id'], '👍', true);
        $service->patchThread($this->ana, $this->space, $this->page, $page['id'], ['resolved' => true]);
        $bob = $service->exportAuthorData($this->bob);
        self::assertEqualsCanonicalizing(['Secret', 'Page'], array_column($bob['comments'], 'body'));
        self::assertSame([['comment_id' => $inline['comments'][0]['id'], 'thread_id' => $inline['id'], 'page_id' => $this->page->getId(), 'emoji' => '👍']], $bob['reactions']);
        self::assertEqualsCanonicalizing([$inline['id'], $page['id']], array_column($bob['threads_started'], 'id'));
        self::assertSame([], $bob['threads_resolved']);
        self::assertSame([$page['id']], array_column($service->exportAuthorData($this->ana)['threads_resolved'], 'id'));

        $erased     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::COMMENT_AUTHOR_ERASED, static function (WikiCommentAuthorErasedEvent $event) use (&$erased): void {
            $erased[] = [$event->user, $event->comments, $event->bodiesDeleted];
        });
        $eraser = new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), $this->guard(), new WikiAuthorLabel('secret'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), dispatcher: $dispatcher);
        self::assertSame(2, $eraser->eraseAuthor($this->bob, false));
        self::assertSame([[$this->bob, 2, false]], $erased);
    }

    private function revise(string $html): void
    {
        $revision = new WikiPageRevision($this->page, ($this->page->getCurrentRevision()?->getRevisionNumber() ?? 0) + 1, $html, $this->ana);
        $this->em->persist($revision);
        $this->page->setCurrentRevision($revision);
        $this->em->flush();
    }

    /**
     * @param array{threads_per_page: int, user_threads_per_page: int, comments_per_thread: int, reactions_per_comment: int, user_reactions_per_comment: int} $limits
     */
    private function service(bool $showNames = true, ?RateLimiterFactory $limiter = null, ?RateLimiterFactory $pageLimiter = null, array $limits = WikiCommentService::DEFAULT_LIMITS): WikiCommentService
    {
        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_REPLIED, WikiEvents::COMMENT_EDITED, WikiEvents::COMMENT_DELETED, WikiEvents::COMMENT_RESOLVED, WikiEvents::COMMENT_REOPENED, WikiEvents::COMMENT_THREAD_DELETED] as $name) {
            $dispatcher->addListener($name, function (WikiCommentEvent $event) use ($name): void {
                $this->events[] = [$name, $event];
            });
        }
        $publisher = new class($this->published) implements WikiCollabPublisherInterface {
            /**
             * @param list<array{0: string, 1: string}> $published
             */
            public function __construct(private array &$published)
            {
            }

            public function publish(string $topic, string $message): void
            {
                $this->published[] = [$topic, $message];
            }
        };
        $authorizer = new class implements WikiCollabSubscriberAuthorizerInterface {
            public function authorize(Request $request, string $topic): void
            {
            }
        };
        $this->avatars = new RecordingAvatarResolver(['id-ana' => '/files/ana/s64', 'id-bob' => null]);
        $translator    = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id === 'wiki.comments.unknown_author' ? 'Deleted user (t)' : $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        return new WikiCommentService(
            new DoctrineOrmWikiCommentRepository($this->em),
            $this->guard(),
            new WikiAuthorLabel('secret', $showNames, new WikiAvatars($this->avatars)),
            new WikiMarkdownRenderer(),
            new WikiHtmlSanitizer(),
            dispatcher: $dispatcher,
            collab: new WikiCollabService(new InMemoryCollabStateStore(), $publisher, $authorizer, ['topic_prefix' => 'eng-topic/page-'], secret: 'secret'),
            publisher: $publisher,
            rateLimiter: $limiter,
            translator: $translator,
            pageRateLimiter: $pageLimiter,
            limits: $limits,
        );
    }

    private function guard(): WikiSpaceGuard
    {
        $grants = &$this->grants;

        return new WikiSpaceGuard(new FixedSpaceLocator($this->space), new class($grants) implements WikiSpaceAccessCheckerInterface {
            /**
             * @param array<string, list<WikiAction>> $grants
             */
            public function __construct(private array &$grants)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return in_array($action, $this->grants[$user->getUserIdentifier()] ?? [], true);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function anchor(string $exact = 'deploy', int $start = 3): array
    {
        return [
            'v'        => 1,
            'quote'    => ['exact' => $exact, 'prefix' => 'We ', 'suffix' => ' daily', 'smuggled' => 'x'],
            'position' => ['start' => $start, 'end' => $start + CommentAnchor::length($exact)],
            'block'    => ['index' => 0, 'heading' => null],
            'extra'    => true,
        ];
    }

    private function assertRefused(int $status, string $reason, callable $call): void
    {
        try {
            $call();
            self::fail('Expected a refusal ' . $status . '.');
        } catch (WikiCommentException $exception) {
            self::assertSame([$status, $reason], [$exception->status, $exception->reason]);
        }
    }
}
