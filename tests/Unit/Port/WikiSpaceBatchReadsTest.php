<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Port;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiApiWikiDirectoryGate;
use Nowo\WikiBundle\Port\WikiSpaceReachGate;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.55.2 / 2.0: the batch space reads of the gates (every space repository has findByIds()), and createInSpace() without
 * the transaction runner.
 */
final class WikiSpaceBatchReadsTest extends TestCase
{
    public function testCreateInSpaceNeedsTheTransactionRunner(): void
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
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->expects(self::never())->method('findById');
        $spaces->expects(self::once())->method('findByIds')->with([$two->getId(), $one->getId(), 'missing'])->willReturn([$two->getId() => $two, $one->getId() => $one, 'missing' => $one]); // a host repository answering a look-alike

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
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
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
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->method('findByIds')->willReturn([]);
        $spaces->method('findById')->willReturn(null);

        $this->expectException(LogicException::class);
        (new WikiApiWikiDirectoryGate($directory, $spaces))->list(new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok', 'token'), new WikiApiWikiFilter());
    }
}
