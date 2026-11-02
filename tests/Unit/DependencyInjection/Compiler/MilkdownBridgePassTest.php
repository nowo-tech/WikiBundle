<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\MilkdownBridgePass;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class MilkdownBridgePassTest extends TestCase
{
    public function testSkipsWithoutTheBridge(): void
    {
        $container = new ContainerBuilder();
        (new MilkdownBridgePass())->process($container);

        self::assertFalse($container->hasDefinition(MilkdownWikiMarkdownRenderer::class));
    }

    public function testHandsTheMilkdownMarkdownOptionsToTheBridge(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MilkdownWikiMarkdownRenderer::class, (new Definition(MilkdownWikiMarkdownRenderer::class))->setArgument('$options', []));
        $container->setParameter('nowo_milkdown_editor.markdown', ['max_input_length' => 5000]);

        (new MilkdownBridgePass())->process($container);

        self::assertSame(['max_input_length' => 5000], $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->getArgument('$options'));
    }

    public function testFailsClosedWithoutNowoMilkdownEditorBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MilkdownWikiMarkdownRenderer::class, new Definition(MilkdownWikiMarkdownRenderer::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('NowoMilkdownEditorBundle');

        (new MilkdownBridgePass())->process($container);
    }
}
