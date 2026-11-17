<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use Nowo\MilkdownEditorBundle\CollabSchema;
use Nowo\WikiBundle\Collaboration\WikiCollabSchema;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * 1.40 (owner decision O7): the collaboration schema version follows Milkdown's and is folded into every new log name.
 */
final class WikiCollabSchemaTest extends TestCase
{
    public function testTheVersionIsMilkdownsAndNewNamesCarryIt(): void
    {
        self::assertSame(CollabSchema::VERSION, WikiCollabSchema::VERSION);
        $epoch = WikiCollabSchema::newEpoch();

        self::assertMatchesRegularExpression('/^s2-[0-9a-f]{28}$/', $epoch);
        self::assertLessThanOrEqual(32, strlen($epoch));
        self::assertNotSame($epoch, WikiCollabSchema::newEpoch());
        self::assertTrue(WikiCollabSchema::isCurrent($epoch));
        self::assertTrue(WikiCollabSchema::isCurrent(null));
        self::assertFalse(WikiCollabSchema::isCurrent('0123456789abcdef0123456789abcdef'));
    }
}
