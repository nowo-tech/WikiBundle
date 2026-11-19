<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\QuickAccess;

use DateTimeImmutable;
use Nowo\WikiBundle\Command\WikiQuickAccessEraseUserCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Form\WikiFavouriteType;
use Nowo\WikiBundle\QuickAccess\WikiFavouriteResult;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function str_repeat;
use function strlen;

/**
 * 1.45: the value types of favourites, recents and backlinks — the entities, {@see WikiFavouriteResult}, the toggle's
 * form ({@see WikiFavouriteType}) and `wiki:quick-access:erase-user`.
 */
final class WikiQuickAccessValuesTest extends TestCase
{
    public function testTheEntities(): void
    {
        $page      = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'a', 'A');
        $favourite = new WikiPageFavourite($page, 'u-1');
        self::assertSame($page, $favourite->getPage());
        self::assertSame('u-1', $favourite->getUserId());
        self::assertSame(36, strlen($favourite->getId()));
        self::assertLessThanOrEqual(new DateTimeImmutable(), $favourite->getCreatedAt());

        $visit = new WikiPageVisit($page, 'u-2', $at = new DateTimeImmutable('2026-11-19 10:00:00'));
        self::assertSame($page, $visit->getPage());
        self::assertSame('u-2', $visit->getUserId());
        self::assertSame($at, $visit->getVisitedAt());
        self::assertSame(36, strlen($visit->getId()));

        $reference = new WikiPageReference($page, 'target-id');
        self::assertSame($page, $reference->getSource());
        self::assertSame('target-id', $reference->getTargetPageId());
        self::assertSame(36, strlen($reference->getId()));
    }

    public function testTheResultSaysWhetherThePageIsAFavourite(): void
    {
        self::assertTrue(WikiFavouriteResult::Added->isFavourite(false));
        self::assertFalse(WikiFavouriteResult::Removed->isFavourite(true));
        self::assertFalse(WikiFavouriteResult::LimitReached->isFavourite(true));
        self::assertTrue(WikiFavouriteResult::Unchanged->isFavourite(true));
        self::assertFalse(WikiFavouriteResult::Unchanged->isFavourite(false));
    }

    public function testTheFormCarriesTheWantedState(): void
    {
        $factory = Forms::createFormFactory();
        $view    = $factory->createNamed('', WikiFavouriteType::class, null, ['favourite' => '0', 'csrf_protection' => false])->createView();
        self::assertSame('0', $view->children['favourite']->vars['value']);
        self::assertSame('', (new WikiFavouriteType())->getBlockPrefix());

        $this->expectException(InvalidOptionsException::class);
        $factory->createNamed('', WikiFavouriteType::class, null, ['favourite' => 'yes', 'csrf_protection' => false]);
    }

    public function testTheEraseCommand(): void
    {
        $em    = SqliteWikiEntityManager::create();
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'a', 'A');
        $em->persist($space);
        $em->persist($page);
        $em->flush();
        $store = new WikiQuickAccessStore(new WikiTransactionRunner($em, backoffBaseMilliseconds: 0));
        $store->addFavourite($page, 'u-1', 10);
        $store->recordVisit($page, 'u-1', 10);

        $tester = new CommandTester(new WikiQuickAccessEraseUserCommand($store, true, true));
        self::assertSame(Command::SUCCESS, $tester->execute(['user-id' => 'u-1']));
        self::assertStringContainsString('2 row(s) erased', $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['user-id' => '']));
        self::assertSame(Command::INVALID, $tester->execute(['user-id' => str_repeat('x', 129)]));
    }
}
