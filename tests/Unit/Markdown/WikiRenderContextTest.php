<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WikiRenderContextTest extends TestCase
{
    private WikiSpace $space;

    private WikiSpace $other;

    protected function setUp(): void
    {
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->other = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
    }

    public function testAFrameExistsOnlyDuringItsRender(): void
    {
        $context = new WikiRenderContext();
        $page    = new WikiPage($this->space, 'p', 'P');
        self::assertNull($context->current());

        $result = $context->within($this->space, $page, function (WikiRenderFrame $frame) use ($context, $page): string {
            self::assertSame($frame, $context->current());
            self::assertSame($this->space, $frame->space);
            self::assertSame($page, $frame->page);
            self::assertNull($frame->reach);

            return 'rendered';
        });

        self::assertSame('rendered', $result);
        self::assertNull($context->current());
    }

    public function testAFailingRenderLeavesNoFrame(): void
    {
        $context = new WikiRenderContext();

        try {
            $context->within($this->space, null, static fn (): never => throw new RuntimeException('boom'));
            self::fail('The failure must reach the caller.');
        } catch (RuntimeException) {
            self::assertNull($context->current());
        }
    }

    public function testANestedRenderKeepsTheOuterReachUnlessItHasItsOwn(): void
    {
        $context = new WikiRenderContext();
        $outer   = $this->reachOnly($this->space);
        $inner   = $this->reachOnly($this->other);

        $context->within($this->space, null, function () use ($context, $outer, $inner): void {
            $context->within($this->other, null, static function (WikiRenderFrame $frame) use ($outer): void {
                self::assertSame($outer, $frame->reach);
            });
            // Its own reach is intersected with the outer one, never a replacement (audit IF-I7).
            $context->within($this->other, null, function (WikiRenderFrame $frame): void {
                self::assertFalse($frame->reaches($this->space));
                self::assertFalse($frame->reaches($this->other));
            }, $inner);
            $context->within($this->space, null, function (WikiRenderFrame $frame): void {
                self::assertTrue($frame->reaches($this->space));
            }, $outer);
        }, $outer);
        $context->within($this->space, null, static function (WikiRenderFrame $frame) use ($inner): void {
            self::assertSame($inner, $frame->reach);
        }, $inner);
    }

    public function testReachAndRecordedLinks(): void
    {
        $open    = new WikiRenderFrame($this->space);
        $limited = new WikiRenderFrame($this->space, null, $this->reachOnly($this->space));

        self::assertTrue($open->reaches($this->other));
        self::assertTrue($limited->reaches($this->space));
        self::assertFalse($limited->reaches($this->other));

        $open->recordPageLink('/wiki/eng/guide', 'id-1');
        $open->recordPageLink('', 'id-2');
        self::assertSame('id-1', $open->pageIdOfLink('/wiki/eng/guide'));
        self::assertNull($open->pageIdOfLink(''));
    }

    public function testResetDropsFramesLeftByAnAbortedRequest(): void
    {
        $context = new WikiRenderContext();
        $context->within($this->space, null, static function () use ($context): void {
            $context->reset();
            self::assertNull($context->current());
        });

        self::assertNull($context->current());
    }

    private function reachOnly(WikiSpace $space): WikiRenderReach
    {
        return new class($space) implements WikiRenderReach {
            public function __construct(private readonly WikiSpace $space)
            {
            }

            public function reachesSpace(WikiSpace $space): bool
            {
                return $space === $this->space;
            }
        };
    }
}
