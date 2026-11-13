<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Repository\WikiPageMetadataQuery;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function array_values;

/**
 * 1.34.0 (`database.user_reference: id`, REQ-ARCH-001 rule 2): `user_class` is a plain security user (no Doctrine
 * entity); revisions, links and comments store its id, names and avatars come from the host's directory, and every
 * ownership check matches the id byte for byte.
 */
final class WikiUserIdReferenceTest extends TestCase
{
    private EntityManager $em;

    private DoctrineWikiUserReferenceResolver $users;

    private WikiUserClassPolicy $classes;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em    = WikiTestDatabase::create(null, WikiMetadataListener::USER_REFERENCE_ID);
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->space);
        $this->em->flush();
        $this->classes = new WikiUserClassPolicy(WikiSecurityUserDto::class);
        $registry      = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $this->users = new DoctrineWikiUserReferenceResolver(WikiSecurityUserDto::class, new WikiEntityManagerProvider($registry), $this->classes, 'id');
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testRevisionsAndLinksStoreTheIdAndTheHistoryNamesComeFromTheDirectory(): void
    {
        $dispatcher = new EventDispatcher();
        $actors     = [];
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, static function (WikiPageSavedEvent $event) use (&$actors): void {
            $actors[] = $event->actorId();
        });
        $ana   = new WikiSecurityUserDto('id-ana', 'ana@x');
        $pages = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher, users: $this->users);
        $page  = $pages->create($this->space, 'Runbook', '<p>v1</p>', $ana);
        $pages->saveRevision($page, 'Runbook', '<p>v2</p>', new WikiSecurityUserDto('Id-ana', 'other@x'));
        $link = (new WikiPageLinkService(new DoctrineOrmWikiPageLinkRepository($this->em), [], $dispatcher, $this->users))->link($page, 'ticket', 'T-1', $ana);
        self::assertEquals(new WikiStoredUserRef('id-ana'), $link->getCreatedBy());
        self::assertSame(['id-ana', 'Id-ana'], $actors);
        $pageId = $page->getId();
        $this->em->clear();

        $page = $this->em->find(WikiPage::class, $pageId);
        self::assertInstanceOf(WikiPage::class, $page);
        $revisions = (new DoctrineOrmWikiPageRevisionRepository($this->em))->findByPage($page);
        self::assertEquals([new WikiStoredUserRef('Id-ana'), new WikiStoredUserRef('id-ana')], array_values(array_map(static fn (WikiPageRevision $r): ?object => $r->getAuthor(), $revisions)));
        self::assertEquals(new WikiStoredUserRef('id-ana'), $this->em->getRepository(WikiPageLink::class)->findAll()[0]->getCreatedBy());

        $directory = new class implements WikiUserDirectoryInterface {
            public function find(string $id): ?object
            {
                return $id === 'id-ana' ? (new TestUserEntity('id-ana'))->withDisplayName('Ana') : null;
            }
        };
        $labels = new WikiAuthorLabel('secret', true, null, new WikiUsers('id', $directory));
        $rows   = (new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), $labels, 30))->rows($page, $revisions);
        self::assertSame('Ana', $rows[1]['author']);
        self::assertTrue($rows[1]['named']);
        self::assertFalse($rows[2]['named'], 'a look-alike id the directory does not know is never named after Ana');
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $rows[2]['author']);
        self::assertSame(1, $rows[2]['saves'], 'the look-alike starts a session of its own');
        self::assertSame($rows[2]['author'], $labels->label(new WikiStoredUserRef('Id-ana'), $page));
        self::assertNotSame($rows[2]['author'], $labels->label(new WikiStoredUserRef('id-bob'), $page));
        self::assertSame('Ana', $labels->label(new WikiStoredUserRef('id-ana'), $page));

        $metadata = (new DoctrineOrmWikiPageRepository($this->em))->pageMetadata($page->getSpace(), new WikiPageMetadataQuery());
        self::assertSame('Id-ana', $metadata[0]->authorId);

        // The thinner groups sessions by the stored id (no IDENTITY() in the id mode).
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $thinned = (new WikiHistoryThinner(new WikiEntityManagerProvider($registry), 0, 30))->thin(new DateTimeImmutable('+1 day'), true);
        self::assertSame(['pages' => 0, 'revisions' => 0], $thinned, 'two authors (by exact id): each keeps its revision');
    }

    public function testCommentsMatchTheViewerByExactId(): void
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
        $comments->editComment($viewer, $this->space, $page, $cid, 'First, edited');
        $comments->react($viewer, $this->space, $page, $cid, '👍', true);
        $comments->reply($viewer, $this->space, $page, $thread['id'], 'Second');
        $comments->patchThread($viewer, $this->space, $page, $thread['id'], ['resolved' => true]);

        // A look-alike viewer owns nothing of Ana's.
        foreach (['Id-ana', "id-\u{e1}na"] as $lookalike) {
            try {
                $comments->editComment(new WikiSecurityUserDto($lookalike), $this->space, $page, $cid, 'Hijacked');
                self::fail($lookalike . ' should not edit');
            } catch (WikiCommentException) {
            }
            self::assertSame([], $comments->exportAuthor(new WikiSecurityUserDto($lookalike)));
        }
        $this->em->clear();

        $stored = $this->em->find(WikiCommentThread::class, $thread['id']);
        self::assertEquals(new WikiStoredUserRef('id-ana'), $stored?->getCreatedBy());
        self::assertEquals(new WikiStoredUserRef('id-ana'), $stored->getResolvedBy());

        $export = $comments->exportAuthorData($viewer);
        self::assertCount(2, $export['comments']);
        self::assertCount(1, $export['reactions']);
        self::assertCount(1, $export['threads_started']);
        self::assertCount(1, $export['threads_resolved']);
        // An erasure by id reaches the rows of a user the host already deleted.
        $resolver = new WikiAuthorResolver($this->createStub(EntityManagerInterface::class), WikiSecurityUserDto::class, new WikiUsers('id'));
        $gone     = $resolver->resolveByIdentifier('id-ana');
        self::assertEquals(new WikiStoredUserRef('id-ana'), $gone);
        self::assertSame(2, $comments->eraseAuthor($gone));
        self::assertSame([], $comments->exportAuthor($viewer));
    }

    public function testTheAuthorResolverInTheIdMode(): void
    {
        $directory = new class implements WikiUserDirectoryInterface {
            public function find(string $id): ?object
            {
                return $id === '7' ? new WikiSecurityUserDto(7) : null;
            }
        };
        $resolver = new WikiAuthorResolver($this->createStub(EntityManagerInterface::class), WikiSecurityUserDto::class, new WikiUsers('id', $directory));

        self::assertInstanceOf(WikiSecurityUserDto::class, $resolver->resolveByIdentifier('7'));
        self::assertEquals(new WikiStoredUserRef('8'), $resolver->resolveByIdentifier('8'));
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolveByIdentifier('7 ');
    }
}
