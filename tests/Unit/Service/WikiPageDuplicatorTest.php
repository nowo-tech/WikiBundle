<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManager;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageDuplicatedEvent;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageArchivedException;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

use function array_map;
use function str_repeat;

/**
 * 1.41: "Duplicate" of the tree menu — the copy's body, format, title, slug, place and icon; the unexposed variant with
 * sub-pages (readable ones only, bounded, cycle-safe).
 */
final class WikiPageDuplicatorTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPageService $service;

    private DoctrineOrmWikiPageRepository $repository;

    private TestUserEntity $user;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->user = new TestUserEntity();
        $this->em->persist($this->user);
        $this->em->flush();
        $this->repository = new DoctrineOrmWikiPageRepository($this->em);
        $this->service    = new WikiPageService($this->repository, new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
    }

    public function testCopiesTheMarkdownBodyTheIconAndPlacesTheCopyBelow(): void
    {
        $first  = $this->service->create($this->space, 'First', '# One', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $second = $this->service->create($this->space, 'Second', 'x', $this->user);
        $this->icons()->setIcon($first, 'tabler:home');

        $result = $this->duplicator($this->icons())->duplicate($first, 'First (copy)', $this->user);
        self::assertSame('first-copy', $result->page->getSlug());
        self::assertSame(WikiPageRevision::FORMAT_MARKDOWN, $result->page->getCurrentRevision()?->getFormat());
        self::assertSame('# One', $result->page->getCurrentRevision()?->getContentMarkdown());
        self::assertSame('tabler:home', $result->page->getIcon());
        self::assertSame(1, $result->index);
        self::assertSame(3, $result->count);
        self::assertGreaterThan($first->getPosition(), $result->page->getPosition());
        self::assertLessThan($second->getPosition(), $result->page->getPosition());
    }

    /** Audit SM-I1: one event per copy (source, copy, actor), for the host's access rules. */
    public function testEveryCopyIsAnnounced(): void
    {
        $root       = $this->service->create($this->space, 'Root', 'r', $this->user);
        $child      = $this->service->create($this->space, 'Child', 'c', $this->user, $root);
        $seen       = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_DUPLICATED, static function (WikiPageDuplicatedEvent $event) use (&$seen): void {
            $seen[] = [$event->source->getTitle(), $event->copy->getTitle(), $event->actorId(), $event->spaceId(), $event->sourceSnapshot()->id, $event->copySnapshot()->id];
        });
        $duplicator = new WikiPageDuplicator($this->service, new WikiPageMover($this->repository, $this->repository), $this->repository, new WikiSlugger(), null, WikiPageDuplicator::MAX_PAGES, $dispatcher);
        $copy       = $duplicator->duplicate($root, 'Root (copy)', $this->user, null, true)->page;

        self::assertCount(2, $seen);
        self::assertSame(['Root', 'Root (copy)', $this->user->getUserIdentifier(), $this->space->getId(), $root->getId(), $copy->getId()], $seen[0]);
        self::assertSame(['Child', 'Child'], [$seen[1][0], $seen[1][1]]);
        self::assertSame($child->getId(), $seen[1][4]);
    }

    public function testAnIconRefusedOnTheCopyLeavesTheCopyWithoutIt(): void
    {
        $page = $this->service->create($this->space, 'Page', 'x', $this->user);
        $this->icons()->setIcon($page, 'tabler:home');
        foreach ([new WikiPageArchivedException('archived'), new TransientStorageFailure(1)] as $failure) {
            $copy = $this->duplicator($this->icons($this->failingSave($failure)))->duplicate($page, 'Copy', $this->user)->page;
            self::assertNull($this->em->getConnection()->fetchOne('SELECT icon FROM wiki_pages WHERE id = ?', [$copy->getId()]));
            $this->em->refresh($copy);
        }
        // Without the icon service, nor an icon: nothing to copy.
        self::assertNull($this->duplicator()->duplicate($page, 'Copy', $this->user)->page->getIcon());
    }

    public function testAPageWithoutARevisionIsNotCopied(): void
    {
        $bare = new WikiPage($this->space, 'bare', 'Bare');
        $this->em->persist($bare);
        $this->em->flush();

        $this->expectException(WikiPageNotFoundException::class);
        $this->duplicator()->duplicate($bare, 'Bare (copy)', $this->user);
    }

    public function testASlugOutsideTheRuleAndALongSlugStillGetAValidCopySlug(): void
    {
        $odd = new WikiPage($this->space, 'Odd_Slug', 'Odd');
        $this->em->persist($odd);
        $this->em->flush();
        $this->service->saveRevision($odd, 'Odd', 'x', $this->user);
        self::assertSame('page-copy', $this->duplicator()->duplicate($odd, 'Odd (copy)', $this->user)->page->getSlug());

        $long = $this->service->create($this->space, 'Long', 'x', $this->user, null, str_repeat('a', 250) . '-b');
        $copy = $this->duplicator()->duplicate($long, 'Long (copy)', $this->user)->page->getSlug();
        self::assertSame(str_repeat('a', 243) . '-copy', $copy);
    }

    public function testWithChildrenCopiesTheReadableSubTreeUnderTheCopy(): void
    {
        $root   = $this->service->create($this->space, 'Root', 'r', $this->user);
        $b      = $this->service->create($this->space, 'B', 'b', $this->user, $root);
        $a      = $this->service->create($this->space, 'A', 'a', $this->user, $root);
        $hidden = $this->service->create($this->space, 'Hidden', 'h', $this->user, $root);
        $this->service->create($this->space, 'Grandchild', 'g', $this->user, $a);
        $this->icons()->setIcon($b, 'tabler:home');
        $visible = [];
        foreach ($this->repository->findActiveBySpace($this->space) as $page) {
            if ($page !== $hidden) {
                $visible[$page->getId()] = true;
            }
        }

        $copy     = $this->duplicator($this->icons())->duplicate($root, 'Root (copy)', $this->user, new WikiPageMoveScope($visible, null), true)->page;
        $children = $this->children($copy);
        self::assertSame(['A', 'B'], array_map(static fn (WikiPage $p): string => $p->getTitle(), $children));
        self::assertSame('tabler:home', $children[1]->getIcon());
        self::assertSame(['Grandchild'], array_map(static fn (WikiPage $p): string => $p->getTitle(), $this->children($children[0])));
        self::assertSame('a-copy', $children[0]->getSlug());

        // Without a scope every active sub-page is copied.
        $all = $this->duplicator()->duplicate($root, 'Root (all)', $this->user, null, true)->page;
        self::assertCount(3, $this->children($all));
    }

    public function testWithChildrenIsBounded(): void
    {
        $root = $this->service->create($this->space, 'Root', 'r', $this->user);
        for ($i = 0; $i < 6; ++$i) {
            $this->service->create($this->space, 'Child ' . $i, 'c', $this->user, $root, 'child-' . $i);
        }
        $copy = $this->duplicator(maxPages: 4)->duplicate($root, 'Root (copy)', $this->user, null, true)->page;
        self::assertCount(3, $this->children($copy));
        self::assertSame(200, WikiPageDuplicator::MAX_PAGES);
    }

    public function testWithChildrenStopsAtAParentCycle(): void
    {
        $root  = $this->service->create($this->space, 'Root', 'r', $this->user);
        $child = $this->service->create($this->space, 'Child', 'c', $this->user, $root);
        // Data written outside the bundle: the root's parent is its own child.
        $root->moveTo($child, 0);
        $this->em->flush();

        $copy = $this->duplicator(maxDepth: 50)->duplicate($child, 'Child (copy)', $this->user, null, true)->page;
        // Root is copied under the copy, but Child (already walked) is not copied again under it.
        self::assertSame(['Root'], array_map(static fn (WikiPage $p): string => $p->getTitle(), $this->children($copy)));
    }

    private function duplicator(?WikiPageIconService $icons = null, int $maxDepth = 10, int $maxPages = WikiPageDuplicator::MAX_PAGES): WikiPageDuplicator
    {
        return new WikiPageDuplicator($this->service, new WikiPageMover($this->repository, $this->repository, maxDepth: $maxDepth), $this->repository, new WikiSlugger(), $icons, $maxPages);
    }

    private function icons(?WikiPageRepositoryInterface $pages = null): WikiPageIconService
    {
        return new WikiPageIconService($pages ?? $this->repository, ['tabler']);
    }

    /** @return list<WikiPage> */
    private function children(WikiPage $parent): array
    {
        /* @var list<WikiPage> */
        return $this->em->getRepository(WikiPage::class)->findBy(['parent' => $parent], ['position' => 'ASC']);
    }

    private function failingSave(Throwable $failure): WikiPageRepositoryInterface
    {
        return new class($this->repository, $failure) implements WikiPageRepositoryInterface {
            public function __construct(private readonly WikiPageRepositoryInterface $inner, private readonly Throwable $failure)
            {
            }

            public function save(WikiPage $page): void
            {
                throw $this->failure;
            }

            public function findById(string $id): ?WikiPage
            {
                return $this->inner->findById($id);
            }

            public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
            {
                return $this->inner->findBySlug($space, $slug);
            }

            public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
            {
                return $this->inner->findActiveBySpace($space, $limit);
            }

            public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
            {
                return $this->inner->findArchivedBySpace($space, $limit);
            }

            public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
            {
                return $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
            }
        };
    }
}
