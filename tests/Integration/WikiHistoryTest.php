<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_keys;
use function array_map;
use function strlen;

/**
 * 1.15: named versions, the history timeline (sessions, author labels) and the retention of `wiki:history:thin`.
 */
final class WikiHistoryTest extends TestCase
{
    private EntityManager $em;

    private WikiPage $page;

    private TestUserEntity $ana;

    private TestUserEntity $bob;

    protected function setUp(): void
    {
        $this->em   = SqliteWikiEntityManager::create();
        $space      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page = new WikiPage($space, 'run', 'Runbooks');
        $this->ana  = new TestUserEntity('ana');
        $this->bob  = new TestUserEntity('bob');
        foreach ([$space, $this->page, $this->ana, $this->bob] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testNamedVersionsAreStoredAndListedPerRevision(): void
    {
        $labels = new DoctrineOrmWikiRevisionLabelRepository($this->em);
        $one    = $this->revision(1, $this->ana, '-2 hours');
        $two    = $this->revision(2, $this->ana, '-1 hour');

        self::assertSame([], $labels->labelsFor([]));
        self::assertNull($labels->findForRevision($one));
        $label = new WikiRevisionLabel($one, 'Before the migration');
        $labels->save($label);
        self::assertSame(36, strlen($label->getId()));
        self::assertSame($one, $label->getRevision());
        self::assertInstanceOf(DateTimeImmutable::class, $label->getCreatedAt());

        $label->setLabel('Before');
        $labels->save($label);
        self::assertSame('Before', $labels->findForRevision($one)?->getLabel());
        self::assertSame([$one->getId() => 'Before'], $labels->labelsFor([$one, $two]));

        $provider = $this->provider();
        self::assertSame([$one->getId() => 'Before'], (new DoctrineOrmWikiRevisionLabelRepository($provider))->labelsFor([$one]));
    }

    public function testTheTimelineGroupsSavesIntoSessions(): void
    {
        // Newest first: Bob (v6) | Ana v5, v4 (one session) | Ana v3 named | Ana v2 an hour before v1 … v1.
        $revisions = [
            $this->revision(6, $this->bob, '-10 minutes'),
            $this->revision(5, $this->ana, '-20 minutes'),
            $this->revision(4, $this->ana, '-30 minutes'),
            $v3 = $this->revision(3, $this->ana, '-40 minutes'),
            $this->revision(2, $this->ana, '-50 minutes'),
            $this->revision(1, $this->ana, '-200 minutes'),
        ];
        $labels = new DoctrineOrmWikiRevisionLabelRepository($this->em);
        $labels->save(new WikiRevisionLabel($v3, 'Reviewed'));

        $rows = (new WikiHistoryTimeline($labels, new WikiAuthorLabel('secret'), 30))->rows($this->page, $revisions);

        self::assertSame([6, 5, 4, 3, 2, 1], array_keys($rows));
        self::assertSame([1, 2, 0, 1, 1, 1], array_map(static fn (array $r): int => $r['saves'], array_values($rows)));
        self::assertSame('Reviewed', $rows[3]['label']);
        self::assertNull($rows[4]['label']);
        self::assertSame($rows[5]['author'], $rows[1]['author']);
        self::assertNotSame($rows[6]['author'], $rows[5]['author']);
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $rows[6]['author']);
        self::assertSame([], (new WikiHistoryTimeline($labels, new WikiAuthorLabel('secret')))->rows($this->page, []));
    }

    public function testAuthorLabelsAreOpaquePerPageUnlessSafeNamesAreShown(): void
    {
        $other = new WikiPage($this->page->getSpace(), 'other', 'Other');
        $named = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'alopez@example.com';
            }

            public function getDisplayName(): string
            {
                return 'Maria  Ruiz';
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $leaky = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'alopez';
            }

            public function getName(): string
            {
                return 'alopez';
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $opaque = new WikiAuthorLabel('secret');
        self::assertNotSame($opaque->label($this->ana, $this->page), $opaque->label($this->ana, $other));
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $opaque->label($named, $this->page));
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $opaque->label(new stdClass(), $this->page));

        $names = new WikiAuthorLabel('secret', true);
        self::assertSame('Maria Ruiz', $names->label($named, $this->page));
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $names->label($leaky, $this->page));
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $names->label($this->ana, $this->page));

        $this->expectException(LogicException::class);
        (new WikiAuthorLabel(''))->label($this->ana, $this->page);
    }

    public function testThinningKeepsOnePerSessionAndDayNamedVersionsAndTheNewest(): void
    {
        $day = (new DateTimeImmutable('-40 days'))->setTime(10, 0);
        // Day D: Ana v1 10:00, v2 10:10, v3 10:20 (one session) | Bob v4 10:25 | Ana v5 13:00, v6 13:05 named.
        // Day D+1: Ana v7 10:00, v8 10:10 | recent: Ana v9 (current).
        $this->revisionAt(1, $this->ana, $day);
        $this->revisionAt(2, $this->ana, $day->modify('+10 minutes'));
        $v3 = $this->revisionAt(3, $this->ana, $day->modify('+20 minutes'));
        $v4 = $this->revisionAt(4, $this->bob, $day->modify('+25 minutes'));
        $this->revisionAt(5, $this->ana, $day->modify('+3 hours'));
        $v6 = $this->revisionAt(6, $this->ana, $day->modify('+3 hours 5 minutes'));
        $this->revisionAt(7, $this->ana, $day->modify('+1 day'));
        $v8 = $this->revisionAt(8, $this->ana, $day->modify('+1 day 10 minutes'));
        $v9 = $this->revision(9, $this->ana, '-1 hour');
        $this->page->setCurrentRevision($v9);
        $other = new WikiPage($this->page->getSpace(), 'fresh', 'Fresh');
        $this->em->persist($other);
        $this->em->flush();
        (new DoctrineOrmWikiRevisionLabelRepository($this->em))->save(new WikiRevisionLabel($v6, 'Signed off'));

        $thinner = new WikiHistoryThinner($this->em, 30, 30);
        self::assertSame(30, $thinner->retentionDays());
        self::assertSame(['pages' => 1, 'revisions' => 3], $thinner->thin(new DateTimeImmutable(), true));
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $this->numbers());

        self::assertSame(['pages' => 1, 'revisions' => 3], $thinner->thin(new DateTimeImmutable()));
        // v1, v2 (older saves of v3's session) and v7 (of v8's) go; the named v6 is a session of its own, so v5 starts one.
        self::assertSame([3, 4, 5, 6, 8, 9], $this->numbers());
        self::assertSame(['pages' => 0, 'revisions' => 0], $thinner->thin(new DateTimeImmutable()));
        self::assertNotNull($v3->getId() . $v4->getId() . $v8->getId());
    }

    public function testThinningNeverRemovesTheNewestOrCurrentRevision(): void
    {
        $day = (new DateTimeImmutable('-40 days'))->setTime(10, 0);
        $this->revisionAt(1, $this->ana, $day);
        $v2 = $this->revisionAt(2, $this->ana, $day->modify('+5 minutes'));
        $this->revisionAt(3, $this->ana, $day->modify('+10 minutes'));
        // The current revision is an older one (v2): both v2 and the newest (v3) stay; v1 goes.
        $this->page->setCurrentRevision($v2);
        $this->em->flush();

        $provider = $this->provider();

        self::assertSame(['pages' => 1, 'revisions' => 1], (new WikiHistoryThinner($provider))->thin(new DateTimeImmutable()));
        self::assertSame([2, 3], $this->numbers());
    }

    private function provider(): WikiEntityManagerProvider
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);

        return new WikiEntityManagerProvider($registry);
    }

    /**
     * @return list<int>
     */
    private function numbers(): array
    {
        $this->em->clear();

        /* @var list<int> */
        return array_map(intval(...), $this->em->createQueryBuilder()->select('r.revisionNumber')->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')->setParameter('page', $this->page->getId())->orderBy('r.revisionNumber')
            ->getQuery()->getSingleColumnResult());
    }

    private function revision(int $number, TestUserEntity $author, string $ago): WikiPageRevision
    {
        return $this->revisionAt($number, $author, new DateTimeImmutable($ago));
    }

    private function revisionAt(int $number, TestUserEntity $author, DateTimeImmutable $at): WikiPageRevision
    {
        $page     = $this->em->find(WikiPage::class, $this->page->getId()) ?? $this->page;
        $revision = new WikiPageRevision($page, $number, '<p>v' . $number . '</p>', $author);
        (new ReflectionProperty(WikiPageRevision::class, 'createdAt'))->setValue($revision, $at);
        $this->em->persist($revision);
        $this->em->flush();

        return $revision;
    }
}
