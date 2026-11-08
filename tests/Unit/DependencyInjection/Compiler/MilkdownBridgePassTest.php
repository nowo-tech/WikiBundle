<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\MilkdownBridgePass;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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

        self::assertSame('%nowo_milkdown_editor.markdown%', $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->getArgument('$options'));
    }

    /**
     * 1.25 `editor.milkdown.host_files`: auto keeps the host file arguments only with Milkdown's host_url_patterns, on
     * always, off never; the tag never reaches the compiled container.
     */
    public function testHostFilesFollowTheMode(): void
    {
        $patterns = ['host_url_patterns' => [['pattern' => '^/f/(?<id>\\w+)$', 'scheme' => 'app-file', 'url' => 'written']]];
        foreach ([
            ['auto', [], false],
            ['auto', $patterns, true],
            [null, $patterns, true],
            ['on', [], true],
            ['off', $patterns, false],
        ] as [$mode, $options, $kept]) {
            $container  = new ContainerBuilder();
            $definition = (new Definition(MilkdownWikiMarkdownRenderer::class))
                ->setArgument('$options', [])
                ->setArgument('$hostSchemes', new Reference('schemes'))
                ->setArgument('$hostFiles', new Reference('files'));
            if ($mode !== null) {
                $definition->addTag(MilkdownBridgePass::HOST_FILES_TAG, ['mode' => $mode]);
            }
            $container->setDefinition(MilkdownWikiMarkdownRenderer::class, $definition);
            $container->setParameter('nowo_milkdown_editor.markdown', $options);

            (new MilkdownBridgePass())->process($container);

            $renderer = $container->getDefinition(MilkdownWikiMarkdownRenderer::class);
            self::assertSame($kept, $renderer->getArgument('$hostSchemes') instanceof Reference, (string) $mode);
            self::assertSame($kept, $renderer->getArgument('$hostFiles') instanceof Reference, (string) $mode);
            self::assertFalse($renderer->hasTag(MilkdownBridgePass::HOST_FILES_TAG));
        }
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
