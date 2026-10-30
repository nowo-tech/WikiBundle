<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

final class WikiLinkAndAttachmentRepositoryTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $user;

    private WikiSpace $space;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $this->em    = SqliteWikiEntityManager::create();
        $this->user  = new TestUserEntity('u-1');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->user);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    public function testLinksAreFoundPerObjectAndPageAndHiddenWhenThePageIsArchived(): void
    {
        $repo     = new DoctrineOrmWikiPageLinkRepository($this->em);
        $runbook  = $this->page('runbook');
        $archived = $this->page('old');

        $link = new WikiPageLink($runbook, 'issue', '42', $this->user);
        $repo->save($link);
        $repo->save(new WikiPageLink($runbook, 'task', '7'));
        $repo->save(new WikiPageLink($archived, 'issue', '42'));
        $archived->archive();
        $this->em->flush();

        self::assertSame($link, $repo->findOne($runbook, 'issue', '42'));
        self::assertNull($repo->findOne($runbook, 'issue', '43'));
        self::assertSame([$link], $repo->findByHostObject('issue', '42'));
        self::assertCount(1, $repo->findByHostObjects('issue', ['42', '7']));
        self::assertCount(1, $repo->findByHostObjects('task', ['7']));
        self::assertSame([], $repo->findByHostObjects('issue', []));
        self::assertCount(2, $repo->findByPage($runbook));
        self::assertSame('issue', $link->getObjectType());
        self::assertSame('42', $link->getObjectId());
        self::assertSame($this->user, $link->getCreatedBy());
        self::assertSame($runbook, $link->getPage());
        self::assertNotSame('', $link->getId());
        self::assertInstanceOf(DateTimeImmutable::class, $link->getCreatedAt());

        $repo->remove($link);
        self::assertNull($repo->findOne($runbook, 'issue', '42'));
    }

    public function testAttachmentsQueriesAndRevisionReferences(): void
    {
        $repo  = new DoctrineOrmWikiAttachmentRepository($this->em);
        $page  = $this->page('runbook');
        $first = new WikiAttachment($page, $this->space, 'shot.png', 'k/1', 'image/png', 100, str_repeat('a', 64), $this->user);
        $other = new WikiAttachment($page, $this->space, 'notes.pdf', 'k/2', 'application/pdf', 50, str_repeat('b', 64));
        $repo->save($first);
        $repo->save($other);

        self::assertSame($first, $repo->findById($first->getId()));
        self::assertSame($page, $first->getPage());
        self::assertSame($this->space, $first->getSpace());
        self::assertSame($this->user, $first->getUploadedBy());
        self::assertInstanceOf(DateTimeImmutable::class, $first->getUploadedAt());
        self::assertSame(100, $first->getSize());
        self::assertNull($repo->findById('missing'));
        self::assertCount(2, $repo->findByIds([$first->getId(), $other->getId()]));
        self::assertSame([], $repo->findByIds([]));
        self::assertCount(2, $repo->findByPage($page));
        self::assertSame(150, $repo->sumSizeBySpace($this->space));
        self::assertCount(2, $repo->findUploadedBefore(new DateTimeImmutable('+1 minute')));
        self::assertSame([], $repo->findUploadedBefore(new DateTimeImmutable('-1 day')));
        self::assertTrue($repo->existsByStorageKey('k/1'));
        self::assertFalse($repo->existsByStorageKey('k/3'));

        $revision = new WikiPageRevision($page, 1, '<p><img src="/wiki/attachments/' . $first->getId() . '"></p>', $this->user);
        $this->em->persist($revision);
        $this->em->flush();
        self::assertTrue($repo->isReferencedByPageRevisions($page, $first->getId()));
        self::assertFalse($repo->isReferencedByPageRevisions($page, $other->getId()));

        $repo->remove($other);
        self::assertSame(100, $repo->sumSizeBySpace($this->space));
    }

    public function testArchivedPagesOfASpace(): void
    {
        $repo = new DoctrineOrmWikiPageRepository($this->em);
        $page = $this->page('gone');
        $this->page('kept');
        $page->archive();
        $this->em->flush();

        self::assertSame([$page], $repo->findArchivedBySpace($this->space));
    }

    private function page(string $slug): WikiPage
    {
        $page = new WikiPage($this->space, $slug, ucfirst($slug));
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }
}
