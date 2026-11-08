<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_diff;
use function array_values;
use function in_array;
use function str_repeat;

/**
 * 1.24 (spec 218): the guard's decisions, each refusal with its code.
 */
final class WikiApiGuardTest extends TestCase
{
    private WikiSpace $eng;

    private WikiSpace $ops;

    private WikiPage $page;

    private WikiPage $child;

    private WikiPage $archived;

    private WikiPage $elsewhere;

    private TestUserEntity $ana;

    /** @var list<WikiAction> */
    private array $granted = [];

    /** @var list<string> */
    private array $roles = ['list', 'create', 'edit', 'history', 'archive'];

    /** @var list<string> */
    private array $hidden = [];

    /** @var array<string, WikiApiWiki> */
    private array $wikis = [];

    protected function setUp(): void
    {
        $this->eng      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'o1');
        $this->ops      = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 'o2');
        $this->page     = new WikiPage($this->eng, 'guide', 'Guide');
        $this->child    = new WikiPage($this->eng, 'install', 'Install', $this->page);
        $this->archived = new WikiPage($this->eng, 'old', 'Old');
        $this->archived->archive();
        $this->elsewhere = new WikiPage($this->ops, 'run', 'Run');
        $this->ana       = new TestUserEntity('u-ana');
        $this->granted   = [WikiAction::View, WikiAction::Edit, WikiAction::Archive];
        $this->wikis     = ['eng' => new WikiApiWiki('eng', $this->eng, 'Eng', ownerKey: 'o1'), 'ops' => new WikiApiWiki('ops', $this->ops, 'Ops', ownerKey: 'o2')];
    }

    public function testWikisAreFoundOnlyWithinReachAndThePlan(): void
    {
        $guard = $this->guard();
        self::assertSame($this->wikis['eng'], $guard->wiki($this->principal(), 'eng'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->wiki($this->principal(), 'Not A Slug'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->wiki($this->principal(), 'missing'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->wiki($this->principal(WikiApiLevel::Full, WikiApiWhere::limitedTo(['o2'], [])), 'eng'));

        // The directory answering another slug than asked is not trusted.
        $this->wikis['alias'] = $this->wikis['eng'];
        $this->refused(404, 'ACCESS_001', fn () => $guard->wiki($this->principal(), 'alias'));

        $this->granted = [];
        $this->refused(404, 'ACCESS_001', fn () => $guard->wiki($this->principal(), 'eng'));
        $this->granted = [WikiAction::View];
        $this->refused(403, 'PLAN_001', fn () => $this->guard(plan: false)->wiki($this->principal(), 'eng'));
    }

    public function testAWikiListedByNameOnlyOrNotOpenedYet(): void
    {
        $guard         = $this->guard();
        $named         = new WikiApiWiki('hr', $this->eng, 'HR', canOpen: false);
        $unopened      = new WikiApiWiki('new', null, 'New');
        $this->granted = [];

        self::assertTrue($guard->sees($this->principal(), $named));
        self::assertTrue($guard->sees($this->principal(), $unopened));
        $this->refused(404, 'ACCESS_001', static fn () => $guard->openSpace($named));
        self::assertNull($guard->openSpace($unopened));
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $unopened, $this->page->getId()));
    }

    public function testPagesBelongToTheWikiAndAreReadableWithTheirAncestors(): void
    {
        $guard = $this->guard();
        $wiki  = $this->wikis['eng'];
        self::assertSame($this->child, $guard->page($this->principal(), $wiki, $this->child->getId()));
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $wiki, 'not-a-uuid'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $wiki, '00000000-0000-4000-8000-000000000000'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $wiki, $this->elsewhere->getId()));
        $this->hidden = ['guide'];
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $wiki, $this->child->getId()));
    }

    public function testArchivedPagesOnlyWhereAskedForAndAllowed(): void
    {
        $guard = $this->guard();
        $wiki  = $this->wikis['eng'];
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(), $wiki, $this->archived->getId()));
        self::assertSame($this->archived, $guard->page($this->principal(WikiApiLevel::Edit), $wiki, $this->archived->getId(), true));
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(WikiApiLevel::Read), $wiki, $this->archived->getId(), true));
        $this->granted = [WikiAction::View];
        $this->refused(404, 'ACCESS_001', fn () => $guard->page($this->principal(WikiApiLevel::Full), $wiki, $this->archived->getId(), true));
    }

    public function testRevisionsByNumberOfThatPageOnly(): void
    {
        $mine  = new WikiPageRevision($this->page, 1, '<p>x</p>', $this->ana);
        $other = new WikiPageRevision($this->elsewhere, 2, '<p>y</p>', $this->ana);
        $guard = $this->guard(revisions: [1 => $mine, 2 => $other]);

        self::assertSame($mine, $guard->revision($this->page, '1'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->revision($this->page, '0'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->revision($this->page, '1x'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->revision($this->page, '2'));
        $this->refused(404, 'ACCESS_001', fn () => $guard->revision($this->page, '3'));
    }

    public function testLevelThenPersonThenRoleGate(): void
    {
        $guard = $this->guard();
        $guard->require($this->principal(WikiApiLevel::Edit), WikiAction::Edit, $this->eng, $this->page);
        $this->refused(403, 'TOKEN_001', fn () => $guard->require($this->principal(WikiApiLevel::Read), WikiAction::Edit, $this->eng, $this->page));
        $this->refused(403, 'ACCESS_002', fn () => $guard->require($this->principal(WikiApiLevel::Full), WikiAction::Restore, $this->eng, $this->page));

        $this->granted = WikiAction::cases();
        foreach ([
            'create'  => WikiAction::Create,
            'edit'    => WikiAction::Restore,
            'history' => WikiAction::History,
            'archive' => WikiAction::Archive,
            'list'    => WikiAction::ViewComments,
        ] as $role => $action) {
            $this->roles = ['list', 'create', 'edit', 'history', 'archive'];
            $guard->require($this->principal(WikiApiLevel::Full), $action, $this->eng);
            $this->roles = array_values(array_diff($this->roles, [$role]));
            $this->refused(403, 'ACCESS_002', fn () => $this->guard()->require($this->principal(WikiApiLevel::Full), $action, $this->eng));
        }
    }

    public function testArchivedWikisAreReadOnly(): void
    {
        $this->guard()->requireWritable($this->wikis['eng']);
        $this->refused(403, 'WIKI_002', fn () => $this->guard()->requireWritable(new WikiApiWiki('old', $this->eng, 'Old', archived: true)));
    }

    public function testEditActsOnlyOnOwnPagesFullOnAnyones(): void
    {
        $bob   = new TestUserEntity('u-bob');
        $guard = $this->guard(revisions: [1 => new WikiPageRevision($this->page, 1, '<p>x</p>', $this->ana)]);

        $guard->requireOwnPage($this->principal(WikiApiLevel::Edit), $this->page);
        $guard->requireOwnPage($this->principal(WikiApiLevel::Full, user: $bob), $this->page);
        $this->refused(403, 'TOKEN_001', fn () => $guard->requireOwnPage($this->principal(WikiApiLevel::Edit, user: $bob), $this->page));
        $this->refused(403, 'TOKEN_001', fn () => $this->guard()->requireOwnPage($this->principal(WikiApiLevel::Edit), $this->page));
    }

    public function testIsUserBySameObjectOrId(): void
    {
        $guard = $this->guard();
        self::assertTrue($guard->isUser($this->principal(), $this->ana));
        self::assertTrue($guard->isUser($this->principal(), new TestUserEntity('u-ana')));
        self::assertFalse($guard->isUser($this->principal(), new TestUserEntity('u-bob')));
        self::assertFalse($guard->isUser($this->principal(), null));
        self::assertFalse($guard->isUser($this->principal(), new stdClass()));
    }

    public function testSourceOnlyForEditorsWithAnEditToken(): void
    {
        $guard = $this->guard();
        self::assertTrue($guard->maySeeSource($this->principal(WikiApiLevel::Edit), $this->eng, $this->page));
        self::assertFalse($guard->maySeeSource($this->principal(WikiApiLevel::Read), $this->eng, $this->page));
        self::assertFalse($guard->maySeeSource($this->principal(WikiApiLevel::Edit), $this->eng, $this->archived));
        $this->roles = ['list'];
        self::assertFalse($this->guard()->maySeeSource($this->principal(WikiApiLevel::Edit), $this->eng, $this->page));
        $this->roles   = ['edit'];
        $this->granted = [WikiAction::View];
        self::assertFalse($this->guard()->maySeeSource($this->principal(WikiApiLevel::Edit), $this->eng, $this->page));
    }

    public function testSlugForms(): void
    {
        $guard = $this->guard(tenants: [['path' => '/p/{spaceSlug}', 'name_prefix' => 'p_', 'slug_requirement' => '[0-9a-f-]{36}--[a-z0-9-]+']]);
        self::assertTrue($guard->isSlug('eng'));
        self::assertTrue($guard->isSlug('0d1c2b3a-4f5e-4a6b-8c7d-9e0f1a2b3c4d--runbooks'));
        self::assertFalse($guard->isSlug('a--b'));
        self::assertFalse($guard->isSlug(str_repeat('a', 121)));
        self::assertFalse($this->guard()->isSlug('0d1c2b3a-4f5e-4a6b-8c7d-9e0f1a2b3c4d--runbooks'));
    }

    public function testTheRenderReachFollowsTheDirectoryAndWhere(): void
    {
        $reach = $this->guard()->reach($this->principal(WikiApiLevel::Read, WikiApiWhere::limitedTo(['o1'], [])));
        self::assertTrue($reach->reachesSpace($this->eng));
        self::assertTrue($reach->reachesSpace($this->eng));
        self::assertFalse($reach->reachesSpace($this->ops));
        self::assertFalse($reach->reachesSpace(new WikiSpace('nobody', 'N', WikiSpaceOwnerScope::Team, 'x')));

        $this->wikis['eng'] = new WikiApiWiki('eng', $this->eng, 'Eng', ownerKey: 'o1', canOpen: false);
        self::assertFalse($this->guard()->reach($this->principal())->reachesSpace($this->eng));
        $this->wikis['eng'] = new WikiApiWiki('eng', $this->ops, 'Eng', ownerKey: 'o1');
        self::assertFalse($this->guard()->reach($this->principal())->reachesSpace($this->eng));
    }

    /**
     * @param array<int, WikiPageRevision> $revisions
     * @param list<array{path: string, name_prefix: string, slug_requirement: string}>|null $tenants
     */
    private function guard(bool $plan = true, array $revisions = [], ?array $tenants = null): WikiApiGuard
    {
        $wikis     = &$this->wikis;
        $directory = $this->createStub(WikiApiDirectoryInterface::class);
        $directory->method('find')->willReturnCallback(static function (WikiApiPrincipal $p, string $slug) use (&$wikis): ?WikiApiWiki {
            return $wikis[$slug] ?? null;
        });
        $directory->method('ofSpace')->willReturnCallback(static function (WikiApiPrincipal $p, WikiSpace $space) use (&$wikis): ?WikiApiWiki {
            return $wikis[$space->getSlug()] ?? null;
        });
        $gate = $this->createStub(WikiApiPlanGateInterface::class);
        $gate->method('allowsWiki')->willReturn($plan);

        $checker = new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => in_array($action, $this->granted, true) && !in_array($page?->getSlug(), $this->hidden, true));

        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $have  = $this->roles;
        $roles->method('canList')->willReturn(in_array('list', $have, true));
        $roles->method('canCreate')->willReturn(in_array('create', $have, true));
        $roles->method('canEdit')->willReturn(in_array('edit', $have, true));
        $roles->method('canViewHistory')->willReturn(in_array('history', $have, true));
        $roles->method('canArchive')->willReturn(in_array('archive', $have, true));

        $byId = [];
        foreach ([$this->page, $this->child, $this->archived, $this->elsewhere] as $page) {
            $byId[$page->getId()] = $page;
        }
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findById')->willReturnCallback(static fn (string $id): ?WikiPage => $byId[$id] ?? null);
        $revisionRepository = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('findByPageAndNumber')->willReturnCallback(static fn (WikiPage $page, int $number): ?WikiPageRevision => $revisions[$number] ?? null);

        return new WikiApiGuard($directory, $gate, $checker, $roles, $pages, $revisionRepository, $tenants === null ? null : new WikiTenantPaths($tenants, []));
    }

    private function principal(WikiApiLevel $level = WikiApiLevel::Full, ?WikiApiWhere $where = null, ?TestUserEntity $user = null): WikiApiPrincipal
    {
        return new WikiApiPrincipal($user ?? $this->ana, $level, $where ?? WikiApiWhere::everything(), 't', 'n');
    }

    private function refused(int $status, string $ecode, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $ecode);
        } catch (WikiApiRefusal $refusal) {
            self::assertSame([$status, $ecode], [$refusal->status, $refusal->ecode]);
        }
    }
}
