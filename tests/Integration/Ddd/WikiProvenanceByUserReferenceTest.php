<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageProvenance;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\RecordingBadgeProvider;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function array_values;

/**
 * 1.36.0: the spec 234 features (1.34) in both host kinds — the standard host (`user_class` a Doctrine entity, stored
 * as a relation) and the DDD host (`database.user_reference: id`, users stored by id). The batch provenance reads the
 * stored ids (`WikiUserFields::identity()`), the badge hook is asked about `user_class` + id
 * (`WikiUserRefs::fromUser()`), and the erasure of a person's actor labels matches their id byte for byte (a
 * look-alike id keeps its labels).
 */
final class WikiProvenanceByUserReferenceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    /** @var class-string */
    private string $userClass;

    private DoctrineWikiUserReferenceResolver $users;

    private WikiEntityManagerProvider $provider;

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostKinds(): iterable
    {
        yield 'standard host (entity)' => [WikiMetadataListener::USER_REFERENCE_ENTITY];
        yield 'DDD host (id)' => [WikiMetadataListener::USER_REFERENCE_ID];
    }

    #[DataProvider('hostKinds')]
    public function testCreatorLastWriteBadgesAndErasureMatchTheExactUser(string $mode): void
    {
        $this->boot($mode);
        $page = $this->writes();
        $this->em->clear();

        // pageProvenance(): the oldest revision's author and the current write, as stored ids.
        $revisions = new DoctrineOrmWikiPageRevisionRepository($this->provider);
        self::assertEquals([$page => new WikiPageProvenance('ana', 'api', 'Look-alike token', 'Ana')], $revisions->pageProvenance([$page, 'missing']));

        // Badges: asked about user_class + exact id, whatever object the row holds (an entity proxy or a stored id).
        $stored = $this->em->find(WikiPage::class, $page);
        self::assertInstanceOf(WikiPage::class, $stored);
        $authors  = array_values(array_map(static fn (WikiPageRevision $r): ?object => $r->getAuthor(), $revisions->findByPage($stored)));
        $provider = new RecordingBadgeProvider(['bob' => [new WikiMemberBadge('Agent', WikiMemberBadge::VARIANT_AGENT)]]);
        $badges   = new WikiMemberBadges($provider, $this->userClass);
        $badges->prime($authors);
        self::assertSame([['Ana', 'bob', 'ana']], $provider->calls);
        self::assertSame(['', 'Agent', ''], array_map(static fn (?object $author): string => ($badges->of($author)[0] ?? null)?->label ?? '', $authors));
        self::assertSame([['Ana', 'bob', 'ana']], $provider->calls, 'served from the memo');

        // Erasure: Ana's label only; the look-alike "Ana" keeps theirs.
        $erasure = new WikiWriteProvenanceErasure($this->provider, $this->users);
        $ana     = $this->user('ana');
        self::assertSame([['revision' => 1, 'channel' => 'web', 'actor_label' => 'Ana in the browser']], array_map(static fn (array $row): array => ['revision' => $row['revision'], 'channel' => $row['channel'], 'actor_label' => $row['actor_label']], $erasure->exportActor($ana)));
        self::assertSame(1, $erasure->eraseActor($ana));
        self::assertSame([], $erasure->exportActor($ana));
        self::assertSame(0, $erasure->eraseOrphaned());
        self::assertSame([null, 'Claude', 'Look-alike token'], $this->em->getConnection()->fetchFirstColumn('SELECT actor_label FROM wiki_page_revisions ORDER BY revision_number'));
    }

    public function testAnIdThatCannotBeMatchedExactlyOwnsNoLabelInTheIdMode(): void
    {
        $this->boot(WikiMetadataListener::USER_REFERENCE_ID);
        $this->writes();
        $erasure = new WikiWriteProvenanceErasure($this->provider);

        self::assertSame([], $erasure->exportActor(new WikiSecurityUserDto(' ana')));
        self::assertSame(0, $erasure->eraseActor(new WikiSecurityUserDto(' ana')));
        self::assertCount(1, $erasure->exportActor(new WikiStoredUserRef('bob')), 'without a resolver the stored id is used as is');
        self::assertSame(['Ana in the browser', 'Claude', 'Look-alike token'], $this->em->getConnection()->fetchFirstColumn('SELECT actor_label FROM wiki_page_revisions ORDER BY revision_number'));
    }

    private function boot(string $mode): void
    {
        $this->em        = WikiTestDatabase::create(null, $mode);
        $this->userClass = $mode === WikiMetadataListener::USER_REFERENCE_ID ? WikiSecurityUserDto::class : TestUserEntity::class;
        $this->space     = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->space);
        if ($mode === WikiMetadataListener::USER_REFERENCE_ENTITY) {
            foreach (['ana', 'Ana', 'bob'] as $id) {
                $this->em->persist(new TestUserEntity($id));
            }
        }
        $this->em->flush();
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $this->provider = new WikiEntityManagerProvider($registry);
        $this->users    = new DoctrineWikiUserReferenceResolver($this->userClass, $this->provider, new WikiUserClassPolicy($this->userClass), $mode);
    }

    /**
     * Ana creates the page from the web, Bob saves from MCP, the look-alike "Ana" from the API.
     *
     * @return string the page id
     */
    private function writes(): string
    {
        $provenance = new class implements WikiWriteProvenanceProviderInterface {
            public ?WikiWriteProvenance $next = null;

            public function current(): ?WikiWriteProvenance
            {
                return $this->next;
            }
        };
        $pages = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher(), users: $this->users, provenance: $provenance);

        $provenance->next = new WikiWriteProvenance('web', 'Ana in the browser');
        $page             = $pages->create($this->space, 'Runbook', '<p>v1</p>', $this->user('ana'));
        $provenance->next = new WikiWriteProvenance('mcp', 'Claude');
        $pages->saveRevision($page, 'Runbook', '<p>v2</p>', $this->user('bob'));
        $provenance->next = new WikiWriteProvenance('api', 'Look-alike token');
        $pages->saveRevision($page, 'Runbook', '<p>v3</p>', $this->user('Ana'));

        return $page->getId();
    }

    private function user(string $id): object
    {
        if ($this->userClass === TestUserEntity::class) {
            $user = $this->em->find(TestUserEntity::class, $id);
            self::assertInstanceOf(TestUserEntity::class, $user);

            return $user;
        }

        return new WikiSecurityUserDto($id, $id . '@example.test');
    }
}
