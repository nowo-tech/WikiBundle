<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use PHPUnit\Framework\TestCase;

final class WikiMilkdownIconHydratorTest extends TestCase
{
    public function testDrawsAllowlistedIconsAndDropsTheOthers(): void
    {
        $hydrator = new WikiMilkdownIconHydrator(new AllowlistIconResolver('/build/tabler.svg', ['home']));
        $html     = '<p>A <span class="nowo-md-icon" data-icon="home" role="img" aria-label="home"></span> B '
            . '<span class="nowo-md-icon" data-icon="bell" role="img" aria-label="bell"></span></p>';

        self::assertSame(
            '<p>A <svg class="nowo-md-icon" role="img" aria-label="home"><use href="/build/tabler.svg#tabler-home"></use></svg> B </p>',
            $hydrator->hydrate($html),
        );
    }

    public function testLeavesHtmlWithoutPlaceholdersAlone(): void
    {
        $hydrator = new WikiMilkdownIconHydrator(new AllowlistIconResolver('/s.svg', ['home']));

        self::assertSame('<p>plain</p>', $hydrator->hydrate('<p>plain</p>'));
        self::assertSame('<span class="nowo-md-icon">x</span>', $hydrator->hydrate('<span class="nowo-md-icon">x</span>'));
    }

    public function testRefusesAnUnsafeSprite(): void
    {
        $icons = new class implements IconResolverInterface {
            public function spriteUrl(string $name): string
            {
                return 'javascript:alert(1)';
            }
        };

        self::assertSame('<p></p>', (new WikiMilkdownIconHydrator($icons))->hydrate('<p><span class="nowo-md-icon" data-icon="home"></span></p>'));
    }
}
