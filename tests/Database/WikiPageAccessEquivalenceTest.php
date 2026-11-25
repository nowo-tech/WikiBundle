<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Security\WikiPageAccess;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_filter;
use function array_map;
use function array_reverse;
use function array_unique;
use function array_values;
use function in_array;
use function sprintf;
use function strtoupper;

/**
 * 1.51 (DDD-host checklist B8): {@see WikiPageAccess::isPageReadable()} and {@see WikiPageAccess::readablePageIds()}
 * decide exactly as {@see WikiSpaceGuard::isPageReadable()} for every person and page of a tree that has hidden branches,
 * an archived page, a second space and a parent cycle — through the same space checker, the person given as a security
 * user, as a reference to that user, or as a reference built from ids only. On SQLite and, through
 * WIKI_TEST_DATABASE_URL, MySQL 8.4, MariaDB 11.4 and PostgreSQL 16.
 */
final class WikiPageAccessEquivalenceTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    /** @var array<string, list<string>> user identifier => page slugs hidden from them ('*' => hidden from everyone) */
    private const HIDDEN = [
        '*'     => ['secret'],
        'u-ana' => ['ops-notes'],
        'u-bob' => ['eng-root', 'cycle-a'],
        'ref-1' => ['leaf'],
    ];

    /** @var list<string> spaces nobody but u-ana may view */
    private const ANA_ONLY_SPACES = ['ops'];

    /** @var list<WikiAction> every decision the checker was asked (the rule asks View only) */
    private array $asked = [];

    /** @var array<string, WikiPage> by slug */
    private array $pages = [];

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $author       = new TestUserEntity('u-ana');
        $eng          = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $ops          = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
        foreach ([$author, $eng, $ops] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $service = LoadReadinessFactory::pages($this->em);
        $page    = (fn (WikiSpace $space, string $slug, ?WikiPage $parent = null): WikiPage => $this->pages[$slug] = $service->create($space, $slug, 'body', $author, $parent, $slug, WikiPageRevision::FORMAT_MARKDOWN));
        $root    = $page($eng, 'eng-root');
        $mid     = $page($eng, 'mid', $root);
        $page($eng, 'leaf', $mid);
        $secret = $page($eng, 'secret', $root);
        $page($eng, 'below-secret', $secret);
        $page($eng, 'archived', $root)->archive();
        $page($eng, 'other-root');
        $notes = $page($ops, 'ops-notes');
        $page($ops, 'ops-child', $notes);
        $cycleA = $page($eng, 'cycle-a');
        $cycleB = $page($eng, 'cycle-b', $cycleA);
        $cycleA->moveTo($cycleB, 0);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testEveryDecisionEqualsTheEntityTypedGuard(): void
    {
        $checker = $this->checker();
        $guard   = new WikiSpaceGuard($this->locator(), $checker);
        $access  = new WikiPageAccess($this->runner, $checker);
        $ana     = new TestUserEntity('u-ana');
        $bob     = new TestUserEntity('u-bob');
        $people  = [
            'security user u-ana' => [$ana, WikiUserRefs::fromUser($ana)],
            'security user u-bob' => [$bob, WikiUserRefs::fromUser($bob)],
            'reference by id'     => [null, WikiUserRef::ofId(TestUserEntity::class, 'ref-1', 'ref-1', ['ROLE_USER'])],
        ];
        $ids     = array_values(array_map(static fn (WikiPage $page): string => $page->getId(), $this->pages));
        $checked = 0;
        foreach ($people as $who => [$user, $ref]) {
            $securityUser = $user ?? WikiUserRefs::securityUser($ref);
            $expected     = [];
            foreach ($this->pages as $slug => $page) {
                $this->em->clear(); // each side reads its own rows: no decision rides on what the other loaded
                $fresh = $this->em->find(WikiPage::class, $page->getId());
                self::assertInstanceOf(WikiPage::class, $fresh);
                $byEntity = $guard->isPageReadable($securityUser, $fresh->getSpace(), $fresh);
                $this->em->clear();
                self::assertSame($byEntity, $access->isPageReadable($ref, $page->getId()), sprintf('%s, page %s', $who, $slug));
                if ($byEntity) {
                    $expected[] = $page->getId();
                }
                ++$checked;
            }
            $this->em->clear();
            self::assertSame($expected, $access->readablePageIds($ref, $ids), $who . ': the batch decides page by page alike');
            // The order asked is kept, duplicates collapse, unknown and look-alike ids are not readable.
            self::assertSame(array_reverse($expected), $access->readablePageIds($ref, [...array_reverse($ids), $ids[0], 'missing', strtoupper($ids[0])]));
        }
        self::assertSame(33, $checked);
        self::assertSame(['view'], array_values(array_unique(array_map(static fn (WikiAction $action): string => $action->value, $this->asked))), 'the rule asks View only');

        // Sanity of the fixture: each person sees a different part of the tree.
        $ana = WikiUserRefs::fromUser($ana);
        self::assertTrue($access->isPageReadable($ana, $this->pages['leaf']->getId()));
        self::assertFalse($access->isPageReadable($ana, $this->pages['below-secret']->getId()), 'below a hidden page');
        self::assertFalse($access->isPageReadable($ana, $this->pages['ops-child']->getId()), 'below a page hidden from her');
        self::assertFalse($access->isPageReadable($ana, $this->pages['cycle-b']->getId()), 'a parent cycle reads as hidden');
        self::assertTrue($access->isPageReadable($people['reference by id'][1], $this->pages['mid']->getId()));
        self::assertFalse($access->isPageReadable($people['reference by id'][1], $this->pages['leaf']->getId()));
        self::assertFalse($access->isPageReadable($people['reference by id'][1], $this->pages['ops-notes']->getId()), 'a space only Ana may view');
        self::assertFalse($access->isPageReadable($ana, 'missing'));
    }

    public function testTheBatchReadsEachTreeLevelOnce(): void
    {
        $access = new WikiPageAccess($this->runner, $this->checker());
        $this->em->clear();
        $ids = array_values(array_map(static fn (WikiPage $page): string => $page->getId(), array_filter($this->pages, static fn (WikiPage $page): bool => in_array($page->getSlug(), ['leaf', 'mid', 'below-secret', 'ops-child'], true))));

        self::assertCount(2, $access->readablePageIds(WikiUserRefs::fromUser(new TestUserEntity('u-ana')), $ids));
        // Every page of those branches is now in the manager: the rule read none of them lazily.
        foreach (['eng-root', 'mid', 'secret', 'ops-notes'] as $slug) {
            $loaded = $this->em->getUnitOfWork()->tryGetById($this->pages[$slug]->getId(), WikiPage::class);
            self::assertInstanceOf(WikiPage::class, $loaded);
            self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($loaded), $slug);
        }
    }

    private function checker(): WikiSpaceAccessCheckerInterface
    {
        $test = $this;

        return new class($test) implements WikiSpaceAccessCheckerInterface {
            public function __construct(private readonly WikiPageAccessEquivalenceTest $test)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return $this->test->decide($user, $action, $space, $page);
            }
        };
    }

    /** @internal the fixture's rule: by person, space and page slug */
    public function decide(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page): bool
    {
        $this->asked[] = $action;
        $who           = $user->getUserIdentifier();
        if ($action !== WikiAction::View || (in_array($space->getSlug(), self::ANA_ONLY_SPACES, true) && $who !== 'u-ana')) {
            return false;
        }

        return !$page instanceof WikiPage || (!in_array($page->getSlug(), self::HIDDEN['*'], true) && !in_array($page->getSlug(), self::HIDDEN[$who] ?? [], true));
    }

    private function locator(): WikiSpaceLocatorInterface
    {
        return new class implements WikiSpaceLocatorInterface {
            public function locate(string $slug, UserInterface $user): ?WikiSpace
            {
                return null;
            }
        };
    }
}
