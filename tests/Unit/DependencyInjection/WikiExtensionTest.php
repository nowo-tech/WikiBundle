<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use LogicException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WikiExtensionTest extends TestCase
{
    public function testRegistersHtmlSanitizerAlias(): void
    {
        $container = new ContainerBuilder();
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiHtmlSanitizerInterface::class));
        self::assertTrue($container->hasDefinition(WikiHtmlSanitizer::class));
    }

    public function testPrependsFrameworkAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new WikiExtension())->prepend($container);

        $configs = $container->getExtensionConfig('framework');
        self::assertNotEmpty($configs);
    }

    public function testPrependsDoctrineMappingsWhenExtensionPresent(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        (new WikiExtension())->prepend($container);

        $configs = $container->getExtensionConfig('doctrine');
        self::assertNotEmpty($configs);
    }

    public function testRegistersNullAiAssistantByDefault(): void
    {
        $container = new ContainerBuilder();
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiAiAssistantInterface::class));
        self::assertSame(NullWikiAiAssistant::class, (string) $container->getAlias(WikiAiAssistantInterface::class));
    }

    public function testAiEnabledWithoutBundleThrows(): void
    {
        if (interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is installed in this environment.');
        }

        $container = new ContainerBuilder();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('symfony/ai-bundle');

        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true],
        ]], $container);
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_wiki', (new WikiExtension())->getAlias());
    }

    public function testRegistersSymfonyAiAssistantWhenEnabled(): void
    {
        if (!interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is not installed in this environment.');
        }

        $container = new ContainerBuilder();
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true, 'agent' => 'wiki_assistant'],
        ]], $container);

        self::assertTrue($container->hasDefinition(SymfonyAiWikiAssistant::class));
        self::assertTrue($container->hasDefinition(WikiKnowledgeSearchTool::class));
        self::assertSame(SymfonyAiWikiAssistant::class, (string) $container->getAlias(WikiAiAssistantInterface::class));
    }
}
