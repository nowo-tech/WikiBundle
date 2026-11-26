<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaimant;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaimErasureListener;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaims;
use Nowo\WikiBundle\Command\WikiCollabSeedClaimsClearCommand;
use Nowo\WikiBundle\Command\WikiCommentsEraseUserCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_column;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 2.0.1 (Beacon 207 T030): wiki:comments:erase-user exports (`collab_seed_claims`, "seeded by you") and clears a
 * person's co-editing seed claims with the rest of their wiki data (with comments on, with only the actor labels, with
 * neither), the comment service's export and erasure event do too, and wiki:collab:seed-claims:clear cleans up the raw
 * identifiers of 2.0.0.
 */
final class WikiCollabSeedClaimsCommandsTest extends TestCase
{
    private const SECRET = 'kernel-secret-for-tests';

    private EntityManager $em;

    private DoctrineWikiCollabStateStore $store;

    private WikiCollabSeedClaims $claims;

    protected function setUp(): void
    {
        $this->em = SqliteWikiEntityManager::create();
        $this->em->persist(new TestUserEntity('ana'));
        $this->em->flush();
        $this->store  = new DoctrineWikiCollabStateStore($this->em);
        $this->claims = new WikiCollabSeedClaims(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0), self::SECRET);
        $this->store->claimSeed('p1', new DateTimeImmutable('+1 minute'), WikiCollabSeedClaimant::user('ana', self::SECRET) . '#5');
        $this->store->claimSeed('p2', new DateTimeImmutable('+1 minute'), 'ana');
        $this->store->claimSeed('p3', new DateTimeImmutable('+1 minute'), 'bob@corp.example#1');
    }

    public function testWithCommentsTheClaimsAreExportedAndCleared(): void
    {
        $tester = $this->tester($this->service());

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        $export = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['p1', 'p2'], array_column($export['collab_seed_claims'], 'page_id'));
        self::assertSame('you', $export['collab_seed_claims'][0]['seeded_by']);
        self::assertStringNotContainsString('h1:', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 co-editing seed claim(s) cleared', $tester->getDisplay());
        self::assertSame([], $this->claims->exportUser($this->ana()));
        self::assertSame('bob@corp.example#1', $this->store->info('p3')->seededBy);
    }

    public function testTheServiceExportAndTheErasureEventCarryTheClaims(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::COMMENT_AUTHOR_ERASED, (new WikiCollabSeedClaimErasureListener($this->claims))->onAuthorErased(...));
        $service = $this->service($this->claims, $dispatcher);

        self::assertCount(2, $service->exportAuthorData($this->ana())['collab_seed_claims']);
        self::assertArrayNotHasKey('collab_seed_claims', $this->service()->exportAuthorData($this->ana()));
        // The command then keeps the service's export (one export, not two).
        $tester = $this->tester($service);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        self::assertCount(2, json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['collab_seed_claims']);

        $service->eraseAuthor($this->ana());
        self::assertSame([], $this->claims->exportUser($this->ana()));
    }

    public function testTheListenerClearsTheClaimsOfTheErasedPerson(): void
    {
        (new WikiCollabSeedClaimErasureListener($this->claims))->onAuthorErased(new WikiCommentAuthorErasedEvent($this->ana(), 0, true));

        self::assertSame([], $this->claims->exportUser($this->ana()));
        self::assertNotNull($this->store->info('p3')->seededBy);
    }

    public function testWithOnlyTheActorLabels(): void
    {
        $tester = $this->tester(null, new WikiWriteProvenanceErasure($this->em));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        self::assertCount(2, json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['collab_seed_claims']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 co-editing seed claim(s) cleared', $tester->getDisplay());
    }

    public function testWithNeitherCommentsNorActorLabels(): void
    {
        $tester = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, TestUserEntity::class), null, null, new WikiQuickAccessStore(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0)), $this->claims));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 co-editing seed claim(s) cleared', $tester->getDisplay());
    }

    public function testTheCleanUpCommand(): void
    {
        $tester = new CommandTester(new WikiCollabSeedClaimsClearCommand($this->claims));

        self::assertSame(Command::FAILURE, $tester->execute(['--batch-size' => '0']));
        self::assertSame(Command::FAILURE, $tester->execute(['--batch-size' => 'x']));
        self::assertSame(Command::FAILURE, $tester->execute(['--batch-size' => '10001']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('2 seed claim(s) to clear. Nothing was changed.', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--batch-size' => '1']));
        self::assertStringContainsString('2 seed claim(s) cleared.', $tester->getDisplay());
        self::assertNull($this->store->info('p2')->seededBy);
        self::assertNotNull($this->store->info('p1')->seededBy, 'hashed claims stay');
        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        self::assertStringContainsString('1 seed claim(s) cleared.', $tester->getDisplay());
        self::assertNull($this->store->info('p1')->seededBy);
    }

    private function ana(): TestUserEntity
    {
        $ana = $this->em->find(TestUserEntity::class, 'ana');
        self::assertInstanceOf(TestUserEntity::class, $ana);

        return $ana;
    }

    private function service(?WikiCollabSeedClaims $claims = null, ?EventDispatcher $dispatcher = null): WikiCommentService
    {
        return new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), new WikiSpaceGuard(new FixedSpaceLocator(), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), dispatcher: $dispatcher, seedClaims: $claims);
    }

    private function tester(?WikiCommentService $comments = null, ?WikiWriteProvenanceErasure $provenance = null): CommandTester
    {
        return new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, TestUserEntity::class), $comments, $provenance, null, $this->claims));
    }
}
