<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Link\WikiPageLinkOperations;
use Nowo\WikiBundle\Link\WikiPageLinkServiceInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageOperations;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiSpaceOperations;
use Nowo\WikiBundle\Service\WikiSpaceServiceInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\User\WikiRefUser;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * 1.32 (REQ-ARCH-001 rule 1): the id operations call the entity ports with the rows the ids name exactly, and refuse
 * unknown or look-alike ids.
 */
final class WikiIdOperationsTest extends TestCase
{
    private WikiSpace $space;

    private WikiSpace $other;

    private WikiPage $page;

    private WikiPage $foreign;

    private WikiPageRevision $revision;

    private WikiPageRevision $foreignRevision;

    protected function setUp(): void
    {
        $this->space           = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $this->other           = new WikiSpace('other', 'Other', WikiSpaceOwnerScope::Team, 'team-2');
        $this->page            = new WikiPage($this->space, 'intro', 'Intro');
        $this->foreign         = new WikiPage($this->other, 'secret', 'Secret');
        $this->revision        = new WikiPageRevision($this->page, 1, '<p>a</p>', null);
        $this->foreignRevision = new WikiPageRevision($this->foreign, 1, '<p>b</p>', null);
    }

    public function testPageOperations(): void
    {
        $service = $this->createMock(WikiPageServiceInterface::class);
        $ops     = new WikiPageOperations($service, $this->pages(), $this->spaces(), $this->revisions());
        $author  = WikiUserRef::ofId(TestUserEntity::class, '7', 'alice');
        $human   = new TestUser('u-1');

        $service->expects(self::once())->method('create')
            ->with($this->space, 'T', 'body', self::isInstanceOf(WikiRefUser::class), $this->page, 'slug', 'markdown')
            ->willReturn($this->page);
        self::assertSame($this->page->getId(), $ops->createPage($this->space->getId(), 'T', 'body', $author, $this->page->getId(), 'slug', 'markdown')->id);

        $service->expects(self::once())->method('saveRevision')
            ->with($this->page, 'T', 'b', $human, 'html', 3)
            ->willReturn($this->revision);
        self::assertSame(1, $ops->saveRevision($this->page->getId(), 'T', 'b', WikiUserRefs::fromUser($human), 'html', 3)->revisionNumber);

        $service->expects(self::once())->method('restore')->with($this->page, $this->revision, $human, 2)->willReturn($this->revision);
        self::assertSame($this->revision->getId(), $ops->restoreRevision($this->page->getId(), $this->revision->getId(), WikiUserRefs::fromUser($human), 2)->id);

        $service->expects(self::exactly(2))->method('archive')->willReturnCallback(function (WikiPage $page, ?object $actor): void {
            self::assertSame($this->page, $page);
        });
        $ops->archivePage($this->page->getId());
        $ops->archivePage($this->page->getId(), $author);
        $service->expects(self::exactly(2))->method('unarchive');
        $ops->unarchivePage($this->page->getId());
        $ops->unarchivePage($this->page->getId(), $author);

        self::assertSame('intro', $ops->findPage($this->page->getId())?->slug);
        self::assertNull($ops->findPage('missing'));
        self::assertNull($ops->findPage('folded'));
    }

    public function testPageOperationsRefuseUnknownIds(): void
    {
        $ops    = new WikiPageOperations($this->createMock(WikiPageServiceInterface::class), $this->pages(), $this->spaces(), $this->revisions());
        $author = WikiUserRef::ofId(TestUserEntity::class, '7');
        $cases  = [
            'unknown space'         => static fn () => $ops->createPage('missing', 'T', 'b', $author),
            'look-alike space'      => static fn () => $ops->createPage('folded', 'T', 'b', $author),
            'parent in other space' => fn () => $ops->createPage($this->space->getId(), 'T', 'b', $author, $this->foreign->getId()),
            'unknown page'          => static fn () => $ops->saveRevision('missing', 'T', 'b', $author),
            'look-alike page'       => static fn () => $ops->archivePage('folded'),
            'unknown revision'      => fn () => $ops->restoreRevision($this->page->getId(), 'missing', $author),
            'look-alike revision'   => fn () => $ops->restoreRevision($this->page->getId(), 'folded', $author),
            'another page revision' => fn () => $ops->restoreRevision($this->page->getId(), $this->foreignRevision->getId(), $author),
        ];
        foreach ($cases as $case => $call) {
            try {
                $call();
                self::fail($case);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSpaceOperations(): void
    {
        $service = $this->createMock(WikiSpaceServiceInterface::class);
        $service->method('create')->willReturn($this->space);
        $service->method('findBySlug')->willReturnCallback(fn (WikiSpaceOwnerScope $scope, string $owner, string $slug): ?WikiSpace => $slug === 'none' ? null : $this->space);
        $ops = new WikiSpaceOperations($service, $this->spaces());

        self::assertSame('docs', $ops->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-1')->slug);
        self::assertSame('docs', $ops->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'docs')?->slug);
        self::assertNull($ops->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'Team-1', 'docs'), 'a look-alike owner is refused');
        self::assertNull($ops->findSpaceBySlug(WikiSpaceOwnerScope::User, 'team-1', 'docs'));
        self::assertNull($ops->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'Docs'));
        self::assertNull($ops->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'none'));
        self::assertSame('docs', $ops->findSpace($this->space->getId())?->slug);
        self::assertNull($ops->findSpace('folded'));
    }

    public function testLinkOperations(): void
    {
        $links = $this->createMock(WikiPageLinkServiceInterface::class);
        $ops   = new WikiPageLinkOperations($links, $this->pages());
        $actor = WikiUserRef::ofId(TestUserEntity::class, '7');

        $links->expects(self::exactly(2))->method('link')->with($this->page, 'task', 'T-1', self::anything());
        $ops->linkPage($this->page->getId(), 'task', 'T-1', $actor);
        $ops->linkPage($this->page->getId(), 'task', 'T-1');
        $links->expects(self::exactly(2))->method('unlink')->willReturn(true);
        self::assertTrue($ops->unlinkPage($this->page->getId(), 'task', 'T-1', $actor));
        self::assertTrue($ops->unlinkPage($this->page->getId(), 'task', 'T-1'));
        $object = new WikiLinkedObject('task', 'T-1', 'One', '/t/1');
        $links->expects(self::once())->method('usedIn')->with($this->page, self::isInstanceOf(WikiRefUser::class))->willReturn(['task' => [$object]]);
        self::assertSame(['task' => [$object]], $ops->usedIn($this->page->getId(), $actor));

        $this->expectException(InvalidArgumentException::class);
        $ops->linkPage('folded', 'task', 'T-1');
    }

    private function pages(): WikiPageRepositoryInterface&MockObject
    {
        $pages = $this->createMock(WikiPageRepositoryInterface::class);
        $pages->method('findById')->willReturnCallback(fn (string $id): ?WikiPage => match ($id) {
            $this->page->getId()    => $this->page,
            'folded'                => $this->page,
            $this->foreign->getId() => $this->foreign,
            default                 => null,
        });

        return $pages;
    }

    private function spaces(): WikiSpaceRepositoryInterface&MockObject
    {
        $spaces = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaces->method('findById')->willReturnCallback(fn (string $id): ?WikiSpace => $id === $this->space->getId() || $id === 'folded' ? $this->space : null);

        return $spaces;
    }

    private function revisions(): WikiPageRevisionRepositoryInterface&MockObject
    {
        $revisions = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisions->method('findById')->willReturnCallback(fn (string $id): ?WikiPageRevision => match ($id) {
            $this->revision->getId()        => $this->revision,
            'folded'                        => $this->revision,
            $this->foreignRevision->getId() => $this->foreignRevision,
            default                         => null,
        });

        return $revisions;
    }
}
