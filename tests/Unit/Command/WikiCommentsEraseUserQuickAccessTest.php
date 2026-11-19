<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Command\WikiCommentsEraseUserCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
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
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_keys;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.45 (audit VC-3): wiki:comments:erase-user exports and erases a person's favourites and recent visits with the rest
 * of their wiki data, with comments on, with only the actor labels, and with neither.
 */
final class WikiCommentsEraseUserQuickAccessTest extends TestCase
{
    private EntityManager $em;

    private WikiQuickAccessStore $store;

    private WikiPage $page;

    protected function setUp(): void
    {
        $this->em   = SqliteWikiEntityManager::create();
        $space      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page = new WikiPage($space, 'run', 'Run');
        foreach ([$space, $this->page, new TestUserEntity('ana')] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->store = new WikiQuickAccessStore(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0));
        $this->store->addFavourite($this->page, 'ana');
        $this->store->recordVisit($this->page, 'ana', 10, 90);
    }

    public function testWithCommentsTheFavouritesAndVisitsAreExportedAndErased(): void
    {
        $tester = $this->tester($this->service());

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        $export = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($this->page->getId(), $export['quick_access']['favourites'][0]['page_id']);
        self::assertCount(1, $export['quick_access']['visits']);

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 favourite(s) and recent visit(s) erased', $tester->getDisplay());
        self::assertSame(['favourites' => [], 'visits' => []], $this->store->exportUser('ana'));
    }

    /** Audit VC2-1: the comment service's own privacy export carries the favourites and visits. */
    public function testTheServiceExportCarriesTheFavouritesAndVisits(): void
    {
        $service = $this->service($this->store);
        $export  = $service->exportAuthorData($this->em->find(TestUserEntity::class, 'ana'));
        self::assertSame($this->page->getId(), $export['quick_access']['favourites'][0]['page_id']);
        self::assertArrayNotHasKey('quick_access', $this->service()->exportAuthorData($this->em->find(TestUserEntity::class, 'ana')));
        self::assertSame([], (fn (object $user): array => $this->quickAccessExport($user))->call($service, new stdClass()));

        // The command then keeps the service's export (one export, not two).
        $tester = $this->tester($service);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        self::assertCount(1, json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['quick_access']['visits']);
    }

    public function testWithOnlyTheActorLabels(): void
    {
        $tester = $this->tester(null, new WikiWriteProvenanceErasure($this->em));

        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana'], ['interactive' => true]));
        self::assertCount(1, $this->store->exportUser('ana')['favourites']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 favourite(s) and recent visit(s) erased', $tester->getDisplay());
    }

    public function testWithOnlyTheQuickAccessRows(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        self::assertSame(['quick_access'], array_keys(json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR)));
        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana'], ['interactive' => true]));
        self::assertStringContainsString('Erase the wiki favourites and recent visits of "ana"?', $tester->getDisplay());
        self::assertCount(1, $this->store->exportUser('ana')['visits']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('2 favourite(s) and recent visit(s) erased', $tester->getDisplay());
    }

    private function service(?WikiQuickAccessStore $store = null): WikiCommentService
    {
        return new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), new WikiSpaceGuard(new FixedSpaceLocator(), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), quickAccess: $store);
    }

    private function tester(?WikiCommentService $comments = null, ?WikiWriteProvenanceErasure $provenance = null): CommandTester
    {
        return new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, TestUserEntity::class), $comments, $provenance, $this->store));
    }
}
