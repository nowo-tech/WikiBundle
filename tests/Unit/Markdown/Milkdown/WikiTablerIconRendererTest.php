<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use InvalidArgumentException;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\Node\TablerIcon;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;
use Nowo\WikiBundle\Markdown\Milkdown\WikiTablerIconRenderer;
use PHPUnit\Framework\TestCase;

final class WikiTablerIconRendererTest extends TestCase
{
    public function testAllowlistedIconsBecomePlaceholdersAndOthersStayText(): void
    {
        $renderer = new WikiTablerIconRenderer(new AllowlistIconResolver('/sprite.svg', ['home']));
        $children = $this->createStub(ChildNodeRendererInterface::class);

        self::assertSame('<span class="nowo-md-icon" data-icon="home" role="img" aria-label="home"></span>', $renderer->render(new TablerIcon('home'), $children));
        self::assertSame(':tabler-bell:', $renderer->render(new TablerIcon('bell'), $children));

        $unsafe = new class implements IconResolverInterface {
            public function spriteUrl(string $name): string
            {
                return 'javascript:alert(1)';
            }
        };
        self::assertSame(':tabler-home:', (new WikiTablerIconRenderer($unsafe))->render(new TablerIcon('home'), $children));
    }

    public function testOtherNodesAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WikiTablerIconRenderer(new AllowlistIconResolver()))->render(new Text('x'), $this->createStub(ChildNodeRendererInterface::class));
    }
}
