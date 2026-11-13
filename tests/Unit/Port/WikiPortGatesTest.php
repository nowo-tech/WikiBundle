<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Port;

use LogicException;
use Nowo\WikiBundle\Ai\WikiAiAnswer;
use Nowo\WikiBundle\Ai\WikiAiAnswererInterface;
use Nowo\WikiBundle\Ai\WikiAiReply;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanPolicyInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiFile;
use Nowo\WikiBundle\Api\Value\WikiApiUpload;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Avatar\WikiAvatarKey;
use Nowo\WikiBundle\Avatar\WikiUserAvatarSourceInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabCookie;
use Nowo\WikiBundle\Collaboration\WikiCollabCookieListener;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriptionPolicyInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Panel\WikiPanelStatesInterface;
use Nowo\WikiBundle\Port\WikiAccessPolicyGate;
use Nowo\WikiBundle\Port\WikiAiAnswererGate;
use Nowo\WikiBundle\Port\WikiApiFileStoreGate;
use Nowo\WikiBundle\Port\WikiApiPlanPolicyGate;
use Nowo\WikiBundle\Port\WikiApiRequestAuthenticatorGate;
use Nowo\WikiBundle\Port\WikiApiWikiDirectoryGate;
use Nowo\WikiBundle\Port\WikiAttachmentLimitsPolicyGate;
use Nowo\WikiBundle\Port\WikiCollabSubscriptionGate;
use Nowo\WikiBundle\Port\WikiObjectLinkSourceGate;
use Nowo\WikiBundle\Port\WikiPanelStatesGate;
use Nowo\WikiBundle\Port\WikiSpaceAccessPolicyGate;
use Nowo\WikiBundle\Port\WikiSpaceIdLocatorGate;
use Nowo\WikiBundle\Port\WikiSpaceReachGate;
use Nowo\WikiBundle\Port\WikiTeamMembershipSourceGate;
use Nowo\WikiBundle\Port\WikiTreeStatesGate;
use Nowo\WikiBundle\Port\WikiUserAvatarSourceGate;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipSourceInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceIdLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tree\WikiTreeStatesInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

use function array_slice;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * 1.33 (REQ-ARCH-001): every gate asks its framework-free twin with value objects, and hands the bundle what the
 * deprecated interface would have.
 */
final class WikiPortGatesTest extends TestCase
{
    private WikiSpace $space;

    private WikiPage $page;

    protected function setUp(): void
    {
        $this->space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $this->page  = new WikiPage($this->space, 'intro', 'Intro');
    }

    public function testAccessPolicyGetsTheUserAsAReference(): void
    {
        $policy = new class implements WikiAccessPolicyInterface {
            /** @var list<array{string, ?WikiUserRef}> */
            public array $asked = [];

            private function ask(string $method, ?WikiUserRef $user): bool
            {
                $this->asked[] = [$method, $user];

                return $user?->hasRole('ROLE_WIKI') ?? false;
            }

            public function canAccess(?WikiUserRef $user = null): bool
            {
                return $this->ask('access', $user);
            }

            public function canList(?WikiUserRef $user = null): bool
            {
                return $this->ask('list', $user);
            }

            public function canCreate(?WikiUserRef $user = null): bool
            {
                return $this->ask('create', $user);
            }

            public function canEdit(?WikiUserRef $user = null): bool
            {
                return $this->ask('edit', $user);
            }

            public function canViewHistory(?WikiUserRef $user = null): bool
            {
                return $this->ask('history', $user);
            }

            public function canArchive(?WikiUserRef $user = null): bool
            {
                return $this->ask('archive', $user);
            }

            public function canAskAi(?WikiUserRef $user = null): bool
            {
                return $this->ask('ai', $user);
            }

            public function canImport(?WikiUserRef $user = null): bool
            {
                return $this->ask('import', $user);
            }

            public function canExport(?WikiUserRef $user = null): bool
            {
                return $this->ask('export', $user);
            }
        };
        $gate = new WikiAccessPolicyGate($policy);
        $user = new TestUser('u-1', 'alice', ['ROLE_WIKI']);

        self::assertTrue($gate->canAccess($user));
        self::assertTrue($gate->canList($user));
        self::assertTrue($gate->canCreate($user));
        self::assertTrue($gate->canEdit($user));
        self::assertTrue($gate->canViewHistory($user));
        self::assertTrue($gate->canArchive($user));
        self::assertTrue($gate->canAskAi($user));
        self::assertTrue($gate->canImport($user));
        self::assertTrue($gate->canExport($user));
        self::assertFalse($gate->canAccess());
        self::assertFalse($gate->canEdit(new TestUser('u-2', 'bob', ['ROLE_USER'])));
        self::assertSame(['access', 'list', 'create', 'edit', 'history', 'archive', 'ai', 'import', 'export'], array_slice(array_column($policy->asked, 0), 0, 9));
        self::assertSame('u-1', $policy->asked[0][1]?->id);
        self::assertSame('alice', $policy->asked[0][1]->identifier);
        self::assertNull($policy->asked[9][1]);
    }

    public function testSpaceAccessPolicyGetsSnapshots(): void
    {
        $policy = new class implements WikiSpaceAccessPolicyInterface {
            /** @var list<array{WikiUserRef, WikiAction, WikiSpaceSnapshot, ?WikiPageSnapshot}> */
            public array $asked = [];

            public function isGranted(WikiUserRef $user, WikiAction $action, WikiSpaceSnapshot $space, ?WikiPageSnapshot $page = null): bool
            {
                $this->asked[] = [$user, $action, $space, $page];

                return $action === WikiAction::View;
            }
        };
        $gate = new WikiSpaceAccessPolicyGate($policy);

        self::assertTrue($gate->isGranted(new TestUser(), WikiAction::View, $this->space, $this->page));
        self::assertFalse($gate->isGranted(new TestUser(), WikiAction::Edit, $this->space));
        self::assertSame($this->space->getId(), $policy->asked[0][2]->id);
        self::assertSame($this->page->getId(), $policy->asked[0][3]?->id);
        self::assertNull($policy->asked[1][3]);
        self::assertSame('user-1', $policy->asked[0][0]->id);
    }

    public function testTeamMembershipKeepsStringIdsOnly(): void
    {
        $source = new class implements WikiTeamMembershipSourceInterface {
            public function teamIdsOf(WikiUserRef $user): array
            {
                /** @var list<string> $ids a host may return anything */
                $ids = ['t-1', 7, 't-2', $user->id];

                return $ids;
            }
        };

        self::assertSame(['t-1', 't-2', 'u-9'], (new WikiTeamMembershipSourceGate($source))->getTeamIdsForUser(new TestUser('u-9')));
    }

    public function testSpaceReachLoadsTheNamedSpacesAndDropsLookAlikes(): void
    {
        $other = new WikiSpace('other', 'Other', WikiSpaceOwnerScope::Team, 'team-2');
        $reach = new class([$this->space->getId(), 'MISSING', $this->space->getId(), 'look', 5, $other->getId()]) implements WikiSpaceReachInterface {
            public ?WikiSpaceSnapshot $checked = null;

            /** @param list<mixed> $ids */
            public function __construct(private readonly array $ids)
            {
            }

            public function spaceIdsFor(WikiUserRef $user): array
            {
                /** @var list<string> $ids */
                $ids = $this->ids;

                return $ids;
            }

            public function canAccessSpace(WikiUserRef $user, WikiSpaceSnapshot $space): bool
            {
                $this->checked = $space;

                return true;
            }
        };
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->method('findById')->willReturnCallback(fn (string $id): ?WikiSpace => match ($id) {
            $this->space->getId() => $this->space,
            'look'                => $other, // a store that folds: the returned id differs
            $other->getId()       => $other,
            default               => null,
        });
        $gate = new WikiSpaceReachGate($reach, $spaces);

        self::assertSame([$this->space, $other], $gate->listSpacesForUser(new TestUser()));
        self::assertTrue($gate->canAccessSpace(new TestUser(), $this->space));
        self::assertSame('docs', $reach->checked?->slug);
    }

    public function testSpaceLocatorRefusesASpaceWhoseIdOrSlugDiffers(): void
    {
        $locator = new class implements WikiSpaceIdLocatorInterface {
            public ?string $answer = null;

            public function locate(string $slug, WikiUserRef $user): ?string
            {
                return $this->answer;
            }
        };
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->method('findById')->willReturnCallback(fn (string $id): ?WikiSpace => $id === 'folded' || $id === $this->space->getId() ? $this->space : null);
        $gate = new WikiSpaceIdLocatorGate($locator, $spaces);

        self::assertNull($gate->locate('docs', new TestUser()));
        $locator->answer = $this->space->getId();
        self::assertSame($this->space, $gate->locate('docs', new TestUser()));
        self::assertNull($gate->locate('Docs', new TestUser()), 'a look-alike slug is not the space asked for');
        $locator->answer = 'folded';
        self::assertNull($gate->locate('docs', new TestUser()), 'a look-alike id is refused');
        $locator->answer = 'missing';
        self::assertNull($gate->locate('docs', new TestUser()));
    }

    public function testObjectLinkSourceKeepsOnlyAskedObjects(): void
    {
        $source = new class implements WikiObjectLinkSourceInterface {
            public function supports(string $objectType): bool
            {
                return $objectType === 'task';
            }

            public function resolveVisible(WikiUserRef $user, string $objectType, array $objectIds): array
            {
                return [
                    '1'  => new WikiLinkedObject('task', '1', 'One', '/t/1'),
                    'x'  => new WikiLinkedObject('task', 'x', 'Not asked', '/t/x'),
                    '2'  => new WikiLinkedObject('task', '3', 'Wrong key', '/t/3'),
                    'X1' => new WikiLinkedObject('task', 'X1', 'Asked as x1', '/t/X1'),
                    '4'  => new WikiLinkedObject('note', '4', 'Other type', '/n/4'),
                ];
            }
        };
        $gate = WikiObjectLinkSourceGate::of($source);
        self::assertInstanceOf(WikiObjectLinkSourceGate::class, $gate);
        self::assertTrue($gate->supports('task'));
        self::assertFalse($gate->supports('note'));
        $visible = $gate->resolveVisible(new TestUser(), 'task', ['1', '2', 'x1', '4']);
        self::assertSame(['1'], array_map(strval(...), array_keys($visible)));

        $legacy = $this->createMock(WikiObjectLinkResolverInterface::class);
        self::assertSame($legacy, WikiObjectLinkSourceGate::of($legacy));
        self::assertNull(WikiObjectLinkSourceGate::of(new stdClass()));
    }

    public function testAvatarSourceIsKeyedLikeTheDeprecatedInterface(): void
    {
        $source = new class implements WikiUserAvatarSourceInterface {
            public function urlFor(WikiUserRef $user, int $size): string
            {
                return '/a/' . $user->id . '/' . $size;
            }

            public function urlsFor(array $users, int $size): array
            {
                return [0 => '/a/' . $users[0]->id, 2 => null];
            }
        };
        $gate  = new WikiUserAvatarSourceGate($source);
        $alice = new TestUser('u-1', 'alice');
        $bob   = new TestUser('u-2', 'bob');
        $carol = new TestUser('u-3', 'carol');

        self::assertSame('/a/u-1/32', $gate->urlFor($alice, 32));
        self::assertSame([WikiAvatarKey::of($alice) => '/a/u-1', WikiAvatarKey::of($carol) => null], $gate->urlsFor([$alice, $bob, $carol], 32));
    }

    public function testPanelAndTreeStatesGetReferencesAndIds(): void
    {
        $panels = new class implements WikiPanelStatesInterface {
            /** @var array<string, bool> */
            public array $open = [];

            public function isOpen(WikiUserRef $user, string $panel): bool
            {
                return $this->open[$user->id . '/' . $panel] ?? false;
            }

            public function setOpen(WikiUserRef $user, string $panel, bool $open): void
            {
                $this->open[$user->id . '/' . $panel] = $open;
            }
        };
        $panelGate = new WikiPanelStatesGate($panels);
        $panelGate->setOpen(new TestUser('u-1'), 'comments', true);
        self::assertTrue($panelGate->isOpen(new TestUser('u-1'), 'comments'));
        self::assertFalse($panelGate->isOpen(new TestUser('u-2'), 'comments'));

        $trees = new class implements WikiTreeStatesInterface {
            /** @var array<string, list<string>> */
            public array $expanded = [];

            public function expanded(WikiUserRef $user, string $spaceId): array
            {
                /** @var list<string> $ids */
                $ids = [...($this->expanded[$spaceId] ?? []), 9];

                return $ids;
            }

            public function setExpanded(WikiUserRef $user, string $spaceId, string $pageId, bool $expanded): void
            {
                $this->expanded[$spaceId][] = $pageId;
            }
        };
        $treeGate = new WikiTreeStatesGate($trees);
        $treeGate->setExpanded(new TestUser(), $this->space, 'p-1', true);
        self::assertSame(['p-1'], $treeGate->expanded(new TestUser(), $this->space));
    }

    public function testCollabSubscriptionQueuesTheGrantedCookies(): void
    {
        $policy = new class implements WikiCollabSubscriptionPolicyInterface {
            public ?WikiRequestContext $request = null;

            /** @var list<string> */
            public array $topics = [];

            public function grant(WikiRequestContext $request, array $topics): array
            {
                $this->request = $request;
                $this->topics  = $topics;

                /** @var list<WikiCollabCookie> $cookies */
                $cookies = [new WikiCollabCookie('hub', 'jwt'), 'not a cookie'];

                return $cookies;
            }
        };
        $gate    = new WikiCollabSubscriptionGate($policy);
        $request = Request::create('/wiki/x/collab', 'POST', server: ['HTTP_COOKIE' => 'PHPSESSID=secret', 'HTTP_AUTHORIZATION' => 'Bearer t']);

        $gate->authorize($request, 'topic-a');
        $gate->authorizeTopics($request, ['topic-a', 'topic-b']);

        self::assertSame(['topic-a', 'topic-b'], $policy->topics);
        self::assertNull($policy->request?->credential);
        self::assertNull($policy->request->header('cookie'));
        self::assertNull($policy->request->header('authorization'));
        $queued = $request->attributes->get(WikiCollabCookieListener::ATTRIBUTE);
        self::assertIsArray($queued);
        self::assertCount(2, $queued);
    }

    public function testApiAuthenticatorGetsTheCredentialOnlyAsCredential(): void
    {
        $authenticator = new class implements WikiApiRequestAuthenticatorInterface {
            public ?WikiRequestContext $request = null;

            public function authenticate(WikiRequestContext $request): WikiApiAuthFailure
            {
                $this->request = $request;

                return WikiApiAuthFailure::Invalid;
            }
        };
        $request = Request::create('/api/wiki/v1/wikis?x=1', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer secret-1', 'HTTP_X_API_KEY' => 'k', 'HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('_route', 'nowo_wiki_api_wikis');

        self::assertSame(WikiApiAuthFailure::Invalid, (new WikiApiRequestAuthenticatorGate($authenticator))->authenticate($request));
        $context = $authenticator->request;
        self::assertNotNull($context);
        self::assertSame('secret-1', $context->credential);
        self::assertSame('GET', $context->method);
        self::assertSame('/api/wiki/v1/wikis', $context->path);
        self::assertSame('nowo_wiki_api_wikis', $context->route);
        self::assertSame(['x' => '1'], $context->query);
        self::assertSame('application/json', $context->header('Accept'));
        self::assertNull($context->header('x-api-key'));
        self::assertNull($context->header('authorization'));
    }

    public function testApiFileStoreGetsValuesAndTheGateBuildsTheDownload(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wiki-gate');
        file_put_contents($path, 'pdf');
        $store = new class($path) implements WikiApiFileStoreInterface {
            public ?WikiPageSnapshot $page = null;

            public ?WikiApiUpload $upload = null;

            public function __construct(private readonly string $path)
            {
            }

            public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPageSnapshot $page): array
            {
                $this->page = $page;

                return [new WikiApiFile('f-1', 'a.pdf', 'file', 'application/pdf', 3)];
            }

            public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiUpload $file): WikiApiUploadedFile
            {
                $this->upload = $file;

                return new WikiApiUploadedFile('f-2', '[a](beacon-file:f-2)');
            }

            public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?WikiApiDownload
            {
                return $fileUuid === 'f-1' ? new WikiApiDownload($this->path, 'a.pdf', 'application/pdf', 3) : null;
            }
        };
        $gate      = new WikiApiFileStoreGate($store);
        $principal = $this->principal();
        $wiki      = new WikiApiWiki('docs', $this->space, 'Docs');

        self::assertCount(1, $gate->list($principal, $wiki, $this->page));
        self::assertSame($this->page->getId(), $store->page?->id);
        $uploaded = $gate->upload($principal, $wiki, new UploadedFile($path, 'a.pdf', 'application/pdf', null, true));
        self::assertSame('f-2', $uploaded->uuid);
        self::assertSame('a.pdf', $store->upload?->clientName);
        self::assertSame('application/pdf', $store->upload->clientMimeType);
        self::assertSame(3, $store->upload->size);
        self::assertSame($path, $store->upload->pathname);
        $response = $gate->download($principal, $wiki, 'f-1');
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertNull($gate->download($principal, $wiki, 'nope'));
        unlink($path);
    }

    public function testApiPlanPolicyGetsAReference(): void
    {
        $policy = new class implements WikiApiPlanPolicyInterface {
            public function allowsWiki(WikiUserRef $user, WikiApiWiki $wiki): bool
            {
                return $user->id === 'u-1' && $wiki->slug === 'docs';
            }
        };
        $gate = new WikiApiPlanPolicyGate($policy);

        self::assertTrue($gate->allowsWiki(new TestUser('u-1'), new WikiApiWiki('docs', null, 'Docs')));
        self::assertFalse($gate->allowsWiki(new TestUser('u-2'), new WikiApiWiki('docs', null, 'Docs')));
    }

    public function testApiDirectoryLoadsTheNamedSpacesAndFailsClosedOnAnUnknownOne(): void
    {
        $directory = new class($this->space->getId()) implements WikiApiWikiDirectoryInterface {
            public ?WikiSpaceSnapshot $of = null;

            public string $provisioned;

            public function __construct(public string $spaceId)
            {
                $this->provisioned = $spaceId;
            }

            public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter): array
            {
                return [new WikiApiWiki('docs', null, 'Docs', spaceId: $this->spaceId), new WikiApiWiki('new', null, 'New')];
            }

            public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki
            {
                return $slug === 'docs' ? new WikiApiWiki('docs', null, 'Docs', spaceId: $this->spaceId) : null;
            }

            public function ofSpace(WikiApiPrincipal $principal, WikiSpaceSnapshot $space): ?WikiApiWiki
            {
                $this->of = $space;

                return $space->slug === 'docs' ? new WikiApiWiki('docs', null, 'Docs', spaceId: $space->id) : null;
            }

            public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): string
            {
                return $this->provisioned;
            }
        };
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->method('findById')->willReturnCallback(fn (string $id): ?WikiSpace => $id === $this->space->getId() || $id === 'folded' ? $this->space : null);
        $gate      = new WikiApiWikiDirectoryGate($directory, $spaces);
        $principal = $this->principal();

        $wikis = $gate->list($principal, new WikiApiWikiFilter());
        self::assertSame($this->space, $wikis[0]->space);
        self::assertNull($wikis[1]->space);
        self::assertNull($wikis[1]->spaceId);
        self::assertSame($this->space, $gate->find($principal, 'docs')?->space);
        self::assertNull($gate->find($principal, 'none'));
        self::assertSame($this->space, $gate->ofSpace($principal, $this->space)?->space);
        self::assertSame('docs', $directory->of?->slug);
        self::assertNull($gate->ofSpace($principal, new WikiSpace('else', 'Else', WikiSpaceOwnerScope::Team, 't')));
        self::assertSame($this->space, $gate->provision($principal, new WikiApiWiki('docs', null, 'Docs')));

        $directory->provisioned = 'folded';
        try {
            $gate->provision($principal, new WikiApiWiki('docs', null, 'Docs'));
            self::fail('a look-alike id must not provision');
        } catch (LogicException $exception) {
            self::assertStringContainsString('does not exist', $exception->getMessage());
        }
        $directory->spaceId = 'missing';
        $this->expectException(LogicException::class);
        $gate->find($principal, 'docs');
    }

    public function testAiAnswererSourcesAreCheckedAgainstTheStore(): void
    {
        $other     = new WikiSpace('other', 'Other', WikiSpaceOwnerScope::Team, 'team-2');
        $otherPage = new WikiPage($other, 'secret', 'Secret');
        $answerer  = new class($this->page, $otherPage) implements WikiAiAnswererInterface {
            public ?WikiSpaceSnapshot $space = null;

            public function __construct(private readonly WikiPage $page, private readonly WikiPage $otherPage)
            {
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function ask(WikiUserRef $user, string $question, ?WikiSpaceSnapshot $space = null): WikiAiReply
            {
                $this->space = $space;

                return new WikiAiReply('42', [
                    ['pageId' => $this->page->getId(), 'spaceId' => $this->page->getSpace()->getId(), 'excerpt' => 'kept'],
                    ['pageId' => 'missing', 'spaceId' => 'x', 'excerpt' => 'no page'],
                    ['pageId' => $this->page->getId(), 'spaceId' => 'wrong', 'excerpt' => 'wrong space'],
                    ['pageId' => $this->otherPage->getId(), 'spaceId' => $this->otherPage->getSpace()->getId(), 'excerpt' => 'not reachable'],
                ]);
            }
        };
        $pages = $this->createMock(WikiPageRepositoryInterface::class);
        $pages->method('findById')->willReturnCallback(fn (string $id): ?WikiPage => match ($id) {
            $this->page->getId() => $this->page,
            $otherPage->getId()  => $otherPage,
            default              => null,
        });
        $access = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $access->method('canAccessSpace')->willReturnCallback(fn (mixed $user, WikiSpace $space): bool => $space === $this->space);
        $gate = new WikiAiAnswererGate($answerer, $pages, $access);

        self::assertTrue($gate->isAvailable());
        $answer = $gate->ask(new TestUser(), 'q?');
        self::assertInstanceOf(WikiAiAnswer::class, $answer);
        self::assertSame('42', $answer->answer);
        self::assertSame([['page' => $this->page, 'space' => $this->space, 'excerpt' => 'kept']], $answer->sources);
        self::assertNull($answerer->space);
        self::assertSame([], $gate->ask(new TestUser(), 'q?', $other)->sources, 'a source outside the asked space is dropped');
        self::assertSame('other', $answerer->space?->slug);
    }

    public function testAttachmentLimitsPolicyGetsASnapshot(): void
    {
        $policy = new class implements WikiAttachmentLimitsPolicyInterface {
            public function limits(WikiSpaceSnapshot $space): WikiAttachmentLimits
            {
                return new WikiAttachmentLimits($space->slug === 'docs' ? 10 : 20);
            }
        };

        self::assertSame(10, (new WikiAttachmentLimitsPolicyGate($policy))->limits($this->space)->maxFileBytes);
    }

    private function principal(): WikiApiPrincipal
    {
        return new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok', 'token');
    }
}
