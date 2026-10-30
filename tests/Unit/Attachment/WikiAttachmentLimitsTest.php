<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Attachment;

use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use PHPUnit\Framework\TestCase;

final class WikiAttachmentLimitsTest extends TestCase
{
    public function testDefaultsRefuseScriptsAndMarkup(): void
    {
        $limits = (new ConfiguredWikiAttachmentLimitsProvider())->limits(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'));

        self::assertSame(10_485_760, $limits->maxFileBytes);
        self::assertSame(524_288_000, $limits->maxSpaceBytes);
        self::assertSame(WikiAttachmentLimits::DEFAULT_ALLOWED_TYPES, $limits->allowedTypes);
        foreach (['svg', 'html', 'htm', 'xml', 'js', 'php', 'exe', 'sh'] as $extension) {
            self::assertArrayNotHasKey($extension, $limits->allowedTypes);
        }

        self::assertNull((new NullWikiAttachmentScanner())->scan('/tmp/x', 'text/plain', 'x.txt'));
    }
}
