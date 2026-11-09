<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiForeignAdminUser;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function array_map;

/**
 * 1.27 (DDD audit 2026-11-09, P1): a security user that is no `user_class` entity writes revisions, links and comments
 * through the user reference resolver; the events give its id as a scalar.
 */
final class WikiNonEntityUserTest extends TestCase
{
    private EntityManager $em;

    private DoctrineWikiUserReferenceResolver $users;

    private WikiSpace $space;

    private WikiUserClassPolicy $classes;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        foreach ([$this->space, (new TestUserEntity('id-ana'))->withDisplayName('Ana')] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->em->clear();
        $this->space = $this->em->find(WikiSpace::class, $this->space->getId()) ?? $this->space;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $this->classes = new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class]);
        $this->users   = new DoctrineWikiUserReferenceResolver(TestUserEntity::class, new WikiEntityManagerProvider($registry), $this->classes);
    }

    public function testPagesAndLinksStoreAReferenceAndEventsGiveTheId(): void
    {
        $dispatcher = new EventDispatcher();
        $ids        = [];
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, static function (WikiPageSavedEvent $event) use (&$ids): void {
            $ids[] = 'saved:' . $event->actorId();
        });
        $dispatcher->addListener(WikiEvents::PAGE_CREATED, static function (WikiPageCreatedEvent $event) use (&$ids): void {
            $ids[] = 'created:' . $event->actorId();
        });
        $dispatcher->addListener(WikiEvents::PAGE_LINKED, static function (WikiPageLinkEvent $event) use (&$ids): void {
            $ids[] = 'linked:' . $event->actorId();
        });
        $viewer = new WikiSecurityUserDto('id-ana');

        $pages = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher, users: $this->users);
        $page  = $pages->create($this->space, 'Runbook', '<p>v1</p>', $viewer);
        $pages->saveRevision($page, 'Runbook', '<p>v2</p>', $viewer);
        $link = (new WikiPageLinkService(new DoctrineOrmWikiPageLinkRepository($this->em), [], $dispatcher, $this->users))->link($page, 'ticket', 'T-1', $viewer);
        self::assertInstanceOf(TestUserEntity::class, $link->getCreatedBy());
        self::assertSame(['saved:id-ana', 'created:id-ana', 'saved:id-ana', 'linked:id-ana'], $ids);
        $pageId = $page->getId();
        $this->em->clear();

        $stored = $this->em->find(WikiPage::class, $pageId);
        self::assertInstanceOf(WikiPage::class, $stored);
        $revision = $stored->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $revision);
        self::assertInstanceOf(TestUserEntity::class, $revision->getAuthor());
        self::assertSame('id-ana', $revision->getAuthor()->getId());
        $links = $this->em->getRepository(WikiPageLink::class)->findAll();
        self::assertCount(1, $links);
    }

    public function testCommentsMatchTheViewerById(): void
    {
        $page = new WikiPage($this->space, 'run', 'Runbook');
        $this->em->persist($page);
        $this->em->flush();
        $guard    = new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker(static fn (WikiAction $action): bool => $action !== WikiAction::ModerateComments));
        $comments = new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), $guard, new WikiAuthorLabel('secret'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), users: $this->users, userClasses: $this->classes);
        $viewer   = new WikiSecurityUserDto('id-ana');

        $thread = $comments->create($viewer, $this->space, $page, null, 'First');
        $cid    = $thread['comments'][0]['id'];
        self::assertIsString($cid);
        // Own comment: editable (matched by id, the identifiers differ).
        $comments->editComment($viewer, $this->space, $page, $cid, 'First, edited');
        $comments->react($viewer, $this->space, $page, $cid, '👍', true);
        $comments->reply($viewer, $this->space, $page, $thread['id'], 'Second');
        $comments->patchThread($viewer, $this->space, $page, $thread['id'], ['resolved' => true]);
        $this->em->clear();

        $stored = $this->em->find(WikiCommentThread::class, $thread['id']);
        self::assertInstanceOf(TestUserEntity::class, $stored?->getCreatedBy());
        self::assertInstanceOf(TestUserEntity::class, $stored->getResolvedBy());

        $export = $comments->exportAuthorData($viewer);
        self::assertCount(2, $export['comments']);
        self::assertCount(1, $export['reactions']);
        self::assertCount(1, $export['threads_started']);
        self::assertCount(1, $export['threads_resolved']);
        self::assertSame(2, $comments->eraseAuthor($viewer));
        self::assertSame([], $comments->exportAuthor($viewer));
    }

    public function testAnotherIdentityDomainWithTheSameIdCannotTouchTheComments(): void
    {
        $page = new WikiPage($this->space, 'faq', 'FAQ');
        $this->em->persist($page);
        $this->em->flush();
        $guard    = new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker(static fn (WikiAction $action): bool => $action !== WikiAction::ModerateComments));
        $comments = new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), $guard, new WikiAuthorLabel('secret'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), users: $this->users, userClasses: $this->classes);
        $customer = $this->em->find(TestUserEntity::class, 'id-ana');
        self::assertInstanceOf(TestUserEntity::class, $customer);
        $thread = $comments->create($customer, $this->space, $page, null, 'Mine');
        $cid    = $thread['comments'][0]['id'];
        self::assertIsString($cid);

        // Admin "id-ana" of another identity domain (not listed): no write as user_class#id-ana, no edit, no delete.
        $admin = new WikiForeignAdminUser('id-ana');
        foreach ([
            'edit'   => fn (): mixed => $comments->editComment($admin, $this->space, $page, $cid, 'Hijacked'),
            'delete' => fn (): mixed => $comments->deleteComment($admin, $this->space, $page, $cid),
        ] as $path => $call) {
            try {
                $call();
                self::fail($path . ' should be refused');
            } catch (WikiCommentException) {
            }
        }
        try {
            $comments->create($admin, $this->space, $page, null, 'As Ana');
            self::fail('create should be refused');
        } catch (AccessDeniedException) {
        }
        self::assertSame(['Mine'], array_map(static fn (array $row): string => $row['body'], $comments->exportAuthor($customer)));

        // Ids 0 / negative are refused even for a listed class.
        foreach ([0, -5, ''] as $id) {
            try {
                $this->users->reference(new WikiSecurityUserDto($id));
                self::fail('id ' . $id . ' should be refused');
            } catch (AccessDeniedException) {
            }
        }
    }
}
