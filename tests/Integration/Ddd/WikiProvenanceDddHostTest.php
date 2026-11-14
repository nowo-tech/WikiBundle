<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Member\WikiMemberRef;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function str_starts_with;

/**
 * Spec 234, REQ-ARCH-001: a DDD host — security users that are no `user_class` entity (stored by id
 * reference), plain-PHP adapters for both ports (no Symfony, Doctrine or HTTP type) keyed by class + id.
 */
final class WikiProvenanceDddHostTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private DoctrineWikiUserReferenceResolver $users;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        foreach ([$this->space, new TestUserEntity('id-ana'), new TestUserEntity('id-bot')] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->em->clear();
        $this->space = $this->em->find(WikiSpace::class, $this->space->getId()) ?? $this->space;
        $registry    = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $this->users = new DoctrineWikiUserReferenceResolver(TestUserEntity::class, new WikiEntityManagerProvider($registry), new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class]));
    }

    public function testPlainAdaptersRecordAndBadgeById(): void
    {
        // The host's request context: the path decides the channel, the token decides the label.
        $context = new class {
            public string $path = '/mcp';

            public ?string $token = 'Claude connector';
        };
        $provenance = new class($context) implements WikiWriteProvenanceProviderInterface {
            public function __construct(private readonly object $context)
            {
            }

            public function current(): ?WikiWriteProvenance
            {
                /** @var object{path: string, token: string|null} $context */
                $context = $this->context;

                return new WikiWriteProvenance(str_starts_with($context->path, '/mcp') ? 'mcp' : 'web', $context->token);
            }
        };
        $badges = new class implements WikiMemberBadgeProviderInterface {
            /** @var list<WikiMemberRef> */
            public array $asked = [];

            public function badgesFor(WikiMemberRef $member): array
            {
                return $this->badgesForMany([$member])[0] ?? [];
            }

            public function badgesForMany(array $members): array
            {
                $answer = [];
                foreach ($members as $index => $member) {
                    $this->asked[] = $member;
                    if ($member->isSameMemberAs(new WikiMemberRef(TestUserEntity::class, 'id-bot'))) {
                        $answer[$index] = [new WikiMemberBadge('Agent', WikiMemberBadge::VARIANT_AGENT)];
                    }
                }

                return $answer;
            }
        };

        $pages          = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher(), users: $this->users, provenance: $provenance);
        $page           = $pages->create($this->space, 'Runbook', '<p>v1</p>', new WikiSecurityUserDto('id-bot'));
        $context->path  = '/wiki/eng';
        $context->token = null;
        $pages->saveRevision($page, 'Runbook', '<p>v2</p>', new WikiSecurityUserDto('id-ana'));
        $pageId = $page->getId();
        $this->em->clear();

        $stored = $this->em->find(WikiPage::class, $pageId);
        self::assertInstanceOf(WikiPage::class, $stored);
        $revisions = (new DoctrineOrmWikiPageRevisionRepository($this->em))->findByPage($stored);
        self::assertSame([['web', null], ['mcp', 'Claude connector']], [[$revisions[0]->getChannel(), $revisions[0]->getActorLabel()], [$revisions[1]->getChannel(), $revisions[1]->getActorLabel()]]);

        $rows = (new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), new WikiAuthorLabel('secret'), 30, new WikiMemberBadges($badges)))->rows($stored, $revisions);
        self::assertSame('Agent', $rows[1]['badges'][0]->label ?? null);
        self::assertSame([], $rows[2]['badges']);
        self::assertCount(2, $badges->asked);
        self::assertSame(TestUserEntity::class, $badges->asked[0]->userClass, 'the stored user class, never a proxy class');

        // Erasure by the host's own security user (an id reference).
        $erasure = new WikiWriteProvenanceErasure($this->em, $this->users);
        self::assertSame('Claude connector', $erasure->exportActor(new WikiSecurityUserDto('id-bot'))[0]['actor_label']);
        self::assertSame(1, $erasure->eraseActor(new WikiSecurityUserDto('id-bot')));
        self::assertSame([], $erasure->exportActor(new WikiSecurityUserDto('id-bot')));
        self::assertNull($this->em->getConnection()->fetchOne('SELECT actor_label FROM wiki_page_revisions WHERE revision_number = 1'));
        self::assertInstanceOf(WikiPageRevision::class, $revisions[1]);
    }
}
