<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Attachment;

use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use PHPUnit\Framework\TestCase;

final class WikiAttachmentReferenceFilterTest extends TestCase
{
    public function testOnlyAttachmentsOfTheSameSpaceSurvive(): void
    {
        $own     = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $foreign = new WikiSpace('hr', 'HR', WikiSpaceOwnerScope::Team, 't');
        $mine    = new WikiAttachment(new WikiPage($own, 'a', 'A'), $own, 'a.png', 'k1', 'image/png', 1, 'x');
        $theirs  = new WikiAttachment(new WikiPage($foreign, 'b', 'B'), $foreign, 'b.png', 'k2', 'image/png', 1, 'x');
        $repo    = new InMemoryAttachmentRepository();
        $repo->save($mine);
        $repo->save($theirs);
        $unknown = '0b9c9a9e-1111-4222-8333-444455556666';

        $html = '<p><img src="/wiki/eng/attachments/' . $mine->getId() . '" alt="mine">'
            . '<img src="/wiki/hr/attachments/' . $theirs->getId() . '" alt="theirs">'
            . '<img src="https://example.com/a.png" alt="external">'
            . '<a href="/wiki/hr/attachments/' . $unknown . '" class="x">doc</a>'
            . '<a href="/wiki/eng/attachments/' . $mine->getId() . '">ok</a></p>';

        $filtered = (new WikiAttachmentReferenceFilter($repo))->filter($own, $html);

        self::assertStringContainsString('alt="mine"', $filtered);
        self::assertStringNotContainsString('theirs', $filtered);
        self::assertStringContainsString('alt="external"', $filtered);
        self::assertStringContainsString('<a class="x">doc</a>', $filtered);
        self::assertStringContainsString('attachments/' . $mine->getId() . '">ok', $filtered);
        self::assertSame('<p>plain</p>', (new WikiAttachmentReferenceFilter($repo))->filter($own, '<p>plain</p>'));
    }
}
