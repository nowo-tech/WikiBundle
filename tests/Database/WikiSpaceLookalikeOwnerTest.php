<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Service\WikiSpaceService;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function json_encode;

/**
 * 1.31.1 (house rule LQ): a look-alike owner id never reaches another owner's spaces. Up to 1.31.0, on MySQL 8 with
 * the default `utf8mb4_0900_ai_ci`, `Alice`, `álice` and `alice` plus a zero-width space listed (and so could open)
 * the personal spaces of `alice`; on MariaDB and on `utf8mb4_unicode_ci` / `_general_ci` also `alice `.
 *
 * Runs on SQLite in memory and, in CI, on MySQL / MariaDB / PostgreSQL ({@see WikiTestDatabase::ENV}). On MySQL /
 * MariaDB it runs twice: with the generated schema (binary NO PAD collation) and after putting the column back on a
 * folding PAD SPACE collation, as on a host that has not run the UPGRADING `ALTER TABLE` yet: the PHP re-check alone
 * must hold there.
 */
final class WikiSpaceLookalikeOwnerTest extends TestCase
{
    private EntityManager $em;

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /** @return iterable<string, array{string|null}> */
    public static function columnCollations(): iterable
    {
        yield 'generated schema' => [null];
        yield 'host not migrated (utf8mb4_unicode_ci)' => ['utf8mb4_unicode_ci'];
    }

    #[DataProvider('columnCollations')]
    public function testLookalikeUserSeesNoForeignPersonalSpace(?string $collation): void
    {
        $this->setUpSchema($collation);
        $this->em->persist(new WikiSpace('notes', 'Alice notes', WikiSpaceOwnerScope::User, 'alice'));
        $this->em->flush();
        $this->em->clear();
        $resolver = new WikiSpaceAccessResolver(new DoctrineOrmWikiSpaceRepository($this->em), new NullWikiTeamMembershipResolver(), 'user');

        self::assertSame(['Alice notes'], $this->names($resolver, 'alice'));
        foreach (['Alice', 'ALICE', "\u{e1}lice", 'alice ', 'alice  ', "alice\u{200B}", "alice\t"] as $lookalike) {
            self::assertSame([], $this->names($resolver, $lookalike), 'look-alike ' . json_encode($lookalike));
        }

        $repository = new DoctrineOrmWikiSpaceRepository($this->em);
        self::assertInstanceOf(WikiSpace::class, $repository->findBySlug(WikiSpaceOwnerScope::User, 'alice', 'notes'));
        self::assertNull($repository->findBySlug(WikiSpaceOwnerScope::User, 'Alice', 'notes'));
        self::assertNull($repository->findBySlug(WikiSpaceOwnerScope::User, 'alice ', 'notes'));
    }

    #[DataProvider('columnCollations')]
    public function testLookalikeTeamIdSeesNoForeignTeamSpace(?string $collation): void
    {
        $this->setUpSchema($collation);
        $this->em->persist(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-a'));
        $this->em->persist(new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-b'));
        $this->em->flush();
        $this->em->clear();
        $teams = new class implements WikiTeamMembershipResolverInterface {
            public function getTeamIdsForUser(UserInterface $user): array
            {
                return [$user->getUserIdentifier()];
            }
        };
        $resolver = new WikiSpaceAccessResolver(new DoctrineOrmWikiSpaceRepository($this->em), $teams, 'team');

        self::assertSame(['Engineering'], $this->names($resolver, 'team-a'));
        foreach (['TEAM-A', 'team-a ', "te\u{e1}m-a"] as $lookalike) {
            self::assertSame([], $this->names($resolver, $lookalike), 'look-alike ' . json_encode($lookalike));
        }
    }

    /** With the exact collation two owners that differ only in case each have their own `notes` space. */
    public function testOwnersDifferingInCaseKeepTheirOwnSpaces(): void
    {
        $this->setUpSchema(null);
        $service = new WikiSpaceService(new DoctrineOrmWikiSpaceRepository($this->em), new WikiSlugger());
        $service->create('Notes', WikiSpaceOwnerScope::User, 'alice');
        $service->create('Notes', WikiSpaceOwnerScope::User, 'Alice');
        $this->em->clear();
        $resolver = new WikiSpaceAccessResolver(new DoctrineOrmWikiSpaceRepository($this->em), new NullWikiTeamMembershipResolver(), 'user');

        self::assertCount(1, $resolver->listSpacesForUser($this->user('alice')));
        self::assertCount(1, $resolver->listSpacesForUser($this->user('Alice')));
        self::assertNotSame(
            $resolver->listSpacesForUser($this->user('alice'))[0]->getId(),
            $resolver->listSpacesForUser($this->user('Alice'))[0]->getId(),
        );
    }

    /** On MySQL / MariaDB the generated column is binary NO PAD (MySQL 5.7: `utf8mb4_bin`). */
    public function testGeneratedOwnerColumnIsExact(): void
    {
        $this->setUpSchema(null);
        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            self::assertNull(WikiSpaceCollationListener::collationFor($platform));

            return;
        }

        self::assertSame(
            WikiSpaceCollationListener::collationFor($platform),
            $connection->fetchOne("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wiki_spaces' AND COLUMN_NAME = 'owner_scope_id'"),
        );
    }

    private function setUpSchema(?string $collation): void
    {
        $this->em = WikiTestDatabase::create();
        if ($collation === null) {
            return;
        }
        if (!$this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('A folding collation exists only on MySQL / MariaDB.');
        }
        $this->em->getConnection()->executeStatement('ALTER TABLE wiki_spaces MODIFY owner_scope_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE ' . $collation . ' NOT NULL');
    }

    /** @return list<string> */
    private function names(WikiSpaceAccessResolver $resolver, string $identifier): array
    {
        return array_map(static fn (WikiSpace $space): string => $space->getName(), $resolver->listSpacesForUser($this->user($identifier)));
    }

    private function user(string $identifier): UserInterface
    {
        return new class($identifier) implements UserInterface {
            public function __construct(private readonly string $identifier)
            {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }
}
