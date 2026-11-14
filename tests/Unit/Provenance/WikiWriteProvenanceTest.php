<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Provenance;

use InvalidArgumentException;
use Nowo\WikiBundle\Provenance\NullWikiWriteProvenanceProvider;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Util\WikiPlainLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function mb_strlen;
use function str_repeat;

/**
 * Spec 234: the write provenance value object — allow-listed channel, plain-text actor label cut to 120.
 */
final class WikiWriteProvenanceTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function channels(): iterable
    {
        foreach (WikiWriteProvenance::CHANNELS as $channel) {
            yield $channel => [$channel];
        }
    }

    #[DataProvider('channels')]
    public function testTheAllowedChannelsAreKept(string $channel): void
    {
        $provenance = new WikiWriteProvenance($channel, 'CI token');

        self::assertSame($channel, $provenance->channel);
        self::assertSame('CI token', $provenance->actorLabel);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedChannels(): iterable
    {
        yield 'unknown' => ['cli'];
        yield 'case' => ['API'];
        yield 'padded' => [' api'];
        yield 'empty' => [''];
    }

    #[DataProvider('refusedChannels')]
    public function testAnyOtherChannelIsRefused(string $channel): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown write channel');

        new WikiWriteProvenance($channel);
    }

    public function testTheActorLabelIsOnePlainLineCutTo120Characters(): void
    {
        self::assertNull((new WikiWriteProvenance('web'))->actorLabel);
        self::assertNull((new WikiWriteProvenance('web', " \t\n "))->actorLabel);
        self::assertSame('Deploy bot (prod)', (new WikiWriteProvenance('api', "  Deploy\u{0000}\tbot\n\u{200B}(prod) "))->actorLabel);
        // HTML is kept as text: every output escapes it.
        self::assertSame('<script>alert(1)</script>', (new WikiWriteProvenance('mcp', '<script>alert(1)</script>'))->actorLabel);

        $long = (new WikiWriteProvenance('app', str_repeat('é', 130)))->actorLabel;
        self::assertSame(str_repeat('é', WikiWriteProvenance::MAX_LABEL), $long);
        self::assertSame(120, mb_strlen((string) $long));
        // Invalid UTF-8 is dropped, never stored.
        self::assertNull((new WikiWriteProvenance('app', "bad \xC3\x28"))->actorLabel);
    }

    public function testThePlainLabelHelper(): void
    {
        self::assertNull(WikiPlainLabel::normalize(null, 10));
        self::assertSame('abc', WikiPlainLabel::normalize('abcdef', 3));
        // A cut that ends on a space leaves no trailing space.
        self::assertSame('ab', WikiPlainLabel::normalize('ab cd', 3));
    }

    public function testTheDefaultProviderRecordsNothing(): void
    {
        self::assertNull((new NullWikiWriteProvenanceProvider())->current());
    }
}
