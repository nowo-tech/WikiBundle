<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Comment;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
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
        $this->ana   = new TestUserEntity('id-ana', 'Ana Garcia');
        $this->bob   = new TestUserEntity('id-bob', 'Bob Stone');
        foreach ([$this->space, $this->page, $this->ana, $this->bob] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $all          = WikiAction::commentActions();
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

    private function service(bool $showNames = true, ?RateLimiterFactory $limiter = null): WikiCommentService
    {
        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::COMMENT_CREATED, WikiEvents::COMMENT_REPLIED, WikiEvents::COMMENT_EDITED, WikiEvents::COMMENT_DELETED, WikiEvents::COMMENT_RESOLVED, WikiEvents::COMMENT_REOPENED] as $name) {
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
            'position' => ['start' => $start, 'end' => $start + strlen($exact)],
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
