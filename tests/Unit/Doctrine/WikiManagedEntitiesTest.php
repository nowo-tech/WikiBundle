<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Doctrine\WikiManagedEntities;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Port\WikiApiWikiDirectoryGate;
use Nowo\WikiBundle\Port\WikiSpaceReachGate;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.55.2: the wiki manager's instance of a host-loaded space or page, and the batch space reads of the gates.
 */
final class WikiManagedEntitiesTest extends TestCase
{
    public function testHeldInstancesAreKeptAndOthersAreReadAgainById(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');
        $page    = new WikiPage($space, 'p', 'P');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('contains')->willReturn(true);
        $manager->expects(self::never())->method('find');
        $managed = new WikiManagedEntities($manager);

        self::assertSame($space, $managed->space($space));
        self::assertSame($page, $managed->page($page));
    }

    public function testALookAlikeOrMissingRowIsNotTheSpaceOrPage(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');
        $other   = new WikiSpace('else', 'Else', WikiSpaceOwnerScope::Team, 't');
        $page    = new WikiPage($space, 'p', 'P');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('contains')->willReturn(false);
        // A folding store answering another row for the id.
        $manager->method('find')->willReturnCallback(static fn (string $class): object => $class === WikiSpace::class ? $other : new WikiPage($other, 'q', 'Q'));
        $managed = new WikiManagedEntities($manager);

        self::assertNull($managed->spaceById($space->getId()));
        try {
            $managed->space($space);
            self::fail('no exact row: refused');
        } catch (LogicException $exception) {
            self::assertStringContainsString($space->getId(), $exception->getMessage());
        }
        $this->expectException(WikiPageNotFoundException::class);
        $managed->page($page);
    }

    public function testTheUiGuardHandsOverTheWikisOwnInstanceOfALocatedSpace(): void
    {
        $host    = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');
        $own     = clone $host;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('contains')->willReturnCallback(static fn (object $entity): bool => $entity === $own);
        $manager->method('find')->willReturn($own);
        $locator = $this->createMock(WikiSpaceLocatorInterface::class);
        $locator->method('locate')->willReturn($host);
        $checker = $this->createMock(WikiSpaceAccessCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with(self::isInstanceOf(UserInterface::class), WikiAction::View, self::identicalTo($own))->willReturn(true);

        self::assertSame($own, (new WikiSpaceGuard($locator, $checker, new WikiManagedEntities($manager)))->requireSpace(new TestUser(), 'docs'));
    }

    public function testCreateInSpaceNeedsTheWikisManager(): void
    {
        $service = new WikiPageService($this->createMock(WikiPageRepositoryInterface::class), $this->createMock(WikiPageRevisionRepositoryInterface::class), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());

        $this->expectException(LogicException::class);
        $service->createInSpace('id', 'Title', 'x', new TestUser());
    }

    public function testTheReachGateReadsEveryNamedSpaceInOneBatch(): void
    {
        $one   = new WikiSpace('one', 'One', WikiSpaceOwnerScope::Team, 't');
        $two   = new WikiSpace('two', 'Two', WikiSpaceOwnerScope::Team, 't');
        $reach = $this->createMock(WikiSpaceReachInterface::class);
        $reach->method('spaceIdsFor')->willReturn([$two->getId(), $one->getId(), $two->getId(), 7, 'missing']);
        $spaces = $this->createMockForIntersectionOfInterfaces([WikiSpaceRepositoryInterface::class, WikiSpaceBatchFinderInterface::class]);
        $spaces->expects(self::never())->method('findById');
        $spaces->expects(self::once())->method('findByIds')->with([$two->getId(), $one->getId(), 'missing'])->willReturn([$two->getId() => $two, $one->getId() => $one]);

        self::assertSame([$two, $one], (new WikiSpaceReachGate($reach, $spaces))->listSpacesForUser(new TestUser()));
    }

    public function testTheDirectoryGateListsTheNamedSpacesInOneBatch(): void
    {
        $named = new WikiSpace('named', 'Named', WikiSpaceOwnerScope::Team, 't');
        $given = new WikiSpace('given', 'Given', WikiSpaceOwnerScope::Team, 't');
        $wikis = [
            new WikiApiWiki('named', null, 'Named', spaceId: $named->getId()),
            new WikiApiWiki('given', $given, 'Given'),
            new WikiApiWiki('unopened', null, 'Unopened'),
        ];
        $directory = $this->createMock(WikiApiWikiDirectoryInterface::class);
        $directory->method('list')->willReturnCallback(static function () use (&$wikis): array {
            return $wikis;
        });
        $spaces = $this->createMockForIntersectionOfInterfaces([WikiSpaceRepositoryInterface::class, WikiSpaceBatchFinderInterface::class]);
        $spaces->expects(self::never())->method('findById');
        $spaces->expects(self::once())->method('findByIds')->with([$named->getId()])->willReturn([$named->getId() => $named]);
        $gate      = new WikiApiWikiDirectoryGate($directory, $spaces);
        $principal = new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok', 'token');

        $listed = $gate->list($principal, new WikiApiWikiFilter());
        self::assertSame($named, $listed[0]->space);
        self::assertSame($given, $listed[1]->space);
        self::assertNull($listed[2]->space);

        $wikis = [new WikiApiWiki('given', $given, 'Given')];
        self::assertSame($given, $gate->list($principal, new WikiApiWikiFilter())[0]->space, 'nothing to read: no batch');
    }

    public function testTheDirectoryGateStillFailsClosedOnASpaceTheBatchDidNotFind(): void
    {
        $directory = $this->createMock(WikiApiWikiDirectoryInterface::class);
        $directory->method('list')->willReturn([new WikiApiWiki('gone', null, 'Gone', spaceId: 'gone-id')]);
        $spaces = $this->createMockForIntersectionOfInterfaces([WikiSpaceRepositoryInterface::class, WikiSpaceBatchFinderInterface::class]);
        $spaces->method('findByIds')->willReturn([]);
        $spaces->method('findById')->willReturn(null);

        $this->expectException(LogicException::class);
        (new WikiApiWikiDirectoryGate($directory, $spaces))->list(new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok', 'token'), new WikiApiWikiFilter());
    }

    public function testGuardsAnswerASpaceTheWikiCannotReadAsMissing(): void
    {
        // Audit ZBO-1: purged meanwhile, or a stale instance the application's manager kept: 404, never a 500.
        $gone    = new WikiSpace('gone', 'Gone', WikiSpaceOwnerScope::Team, 't');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('contains')->willReturn(false);
        $manager->method('find')->willReturn(null);
        $managed = new WikiManagedEntities($manager);
        $checker = $this->createMock(WikiSpaceAccessCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $locator = $this->createMock(WikiSpaceLocatorInterface::class);
        $locator->method('locate')->willReturn($gone);
        try {
            (new WikiSpaceGuard($locator, $checker, $managed))->requireSpace(new TestUser(), 'gone');
            self::fail('a space the wiki cannot read is missing');
        } catch (NotFoundHttpException) {
        }

        $directory = $this->createMock(WikiApiDirectoryInterface::class);
        $directory->method('find')->willReturn(new WikiApiWiki('gone', $gone, 'Gone'));
        $guard = new WikiApiGuard($directory, $this->createMock(WikiApiPlanGateInterface::class), $checker, $this->createMock(WikiAccessCheckerInterface::class), $this->createMock(WikiPageRepositoryInterface::class), $this->createMock(WikiPageRevisionRepositoryInterface::class), null, null, $managed);
        try {
            $guard->wiki(new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok', 'token'), 'gone');
            self::fail('a space the wiki cannot read is missing');
        } catch (WikiApiRefusal $refusal) {
            self::assertSame(404, $refusal->status);
        }
    }
}
