<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use PHPUnit\Framework\TestCase;

final class WikiCollabEntitiesTest extends TestCase
{
    public function testAccessors(): void
    {
        $document = new WikiCollabDocument('p1');
        self::assertSame('p1', $document->getPageId());
        self::assertNull($document->getBaseRevisionId());
        self::assertNull($document->getSeededAt());
        self::assertSame(0, $document->getRecentResets());
        self::assertNull($document->getLastResetAt());
        self::assertInstanceOf(DateTimeImmutable::class, $document->getUpdatedAt());

        self::assertSame('r1', (new WikiCollabDocument('p2', 'r1'))->getBaseRevisionId());
        $update = new WikiCollabUpdate('p1', WikiCollabUpdate::KIND_SNAPSHOT, 'AQ==', 3);
        self::assertNull($update->getId());
        self::assertSame(9, (new WikiCollabUpdate('p1', WikiCollabUpdate::KIND_UPDATE, 'AQ==', null, 9))->getId());
        self::assertSame('p1', $update->getPageId());
        self::assertSame('snapshot', $update->getKind());
        self::assertSame('AQ==', $update->getData());
        self::assertSame(3, $update->getCoversSeq());
        self::assertInstanceOf(DateTimeImmutable::class, $update->getCreatedAt());
    }
}
