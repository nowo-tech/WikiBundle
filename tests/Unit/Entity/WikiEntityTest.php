<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

final class WikiEntityTest extends TestCase
{
    public function testSpaceAndPageLifecycle(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $space->setName('Eng');

        $page = new WikiPage($space, 'runbook', 'Runbook');
        $page->setTitle('Runbook v2');
        $page->setPosition(3);
        $page->archive();

        self::assertSame('Eng', $space->getName());
        self::assertTrue($page->isArchived());
        self::assertSame(3, $page->getPosition());
        self::assertInstanceOf(DateTimeImmutable::class, $page->getArchivedAt());
    }

    public function testRevisionStoresContent(): void
    {
        $page     = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::User, 'u'), 'p', 'P');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', new TestUser());

        $page->setCurrentRevision($revision);

        self::assertSame(1, $revision->getRevisionNumber());
        self::assertSame('<p>x</p>', $revision->getContentHtml());
        self::assertSame($revision, $page->getCurrentRevision());
    }

    public function testEntityGetters(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $child = new WikiPage($space, 'child', 'Child');
        $page  = new WikiPage($space, 'parent', 'Parent', $child);

        self::assertNotEmpty($space->getId());
        self::assertSame('eng', $space->getSlug());
        self::assertSame('Engineering', $space->getName());
        self::assertSame(WikiSpaceOwnerScope::Team, $space->getOwnerScopeType());
        self::assertSame('team-1', $space->getOwnerScopeId());
        self::assertInstanceOf(DateTimeImmutable::class, $space->getCreatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $space->getUpdatedAt());

        self::assertNotEmpty($page->getId());
        self::assertSame('parent', $page->getSlug());
        self::assertSame('Parent', $page->getTitle());
        self::assertSame($space, $page->getSpace());
        self::assertSame($child, $page->getParent());
        self::assertInstanceOf(DateTimeImmutable::class, $page->getCreatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $page->getUpdatedAt());

        $user     = new TestUser();
        $revision = new WikiPageRevision($page, 2, '<p>v2</p>', $user);
        self::assertNotEmpty($revision->getId());
        self::assertSame($page, $revision->getPage());
        self::assertSame($user, $revision->getAuthor());
        self::assertInstanceOf(DateTimeImmutable::class, $revision->getCreatedAt());
    }
}
