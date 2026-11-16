<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function interface_exists;

/**
 * 1.39: page_icon / space_icon .picker and .emoji, tree.context_menu, web_ui.page_nav.
 */
final class WikiIconPickerConfigTest extends TestCase
{
    public function testDefaultsKeepThe138Behaviour(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User']]);

        self::assertSame('text', $config['page_icon']['picker']);
        self::assertFalse($config['page_icon']['emoji']);
        self::assertSame('text', $config['space_icon']['picker']);
        self::assertFalse($config['space_icon']['emoji']);
        self::assertSame('none', $config['web_ui']['page_nav']);
        self::assertSame(
            ['page' => ['picker' => 'text', 'emoji' => false, 'prefixes' => ['tabler'], 'icon_prefixes' => ['tabler']], 'space' => ['picker' => 'text', 'emoji' => false, 'prefixes' => ['tabler'], 'icon_prefixes' => ['tabler']]],
            WikiExtension::iconPicker($config),
        );
    }

    public function testParametersOfThePickersAndTheMenu(): void
    {
        if (!interface_exists(WikiExtension::ICON_SELECTOR_EMOJI_CATALOG)) {
            self::markTestSkipped('nowo-tech/icon-selector-bundle >= 1.6 (dev dependency) is not installed.');
        }
        $container = $this->load([
            'space_icon' => ['allowed_prefixes' => ['tabler', 'lucide'], 'picker' => 'icon_selector'],
            'page_icon'  => ['form_field' => true, 'picker' => 'icon_selector', 'emoji' => true],
            'tree'       => ['enabled' => true],
            'web_ui'     => ['page_nav' => 'both'],
        ]);

        self::assertSame(['tabler', 'lucide', 'emoji'], $container->getParameter('nowo_wiki.page_icon.accepted_prefixes'));
        self::assertSame(['tabler', 'lucide'], $container->getParameter('nowo_wiki.space_icon.accepted_prefixes'));
        self::assertSame('icon_selector', $container->getParameter('nowo_wiki.space_icon.picker'));
        self::assertTrue($container->getParameter('nowo_wiki.tree.context_menu'));
        /** @var array<string, mixed> $layout */
        $layout = $container->getParameter('nowo_wiki.web_ui.layout');
        self::assertSame('both', $layout['page_nav']);
    }

    public function testTheMenuIsOffWithoutTheTreeOrWhenDisabled(): void
    {
        self::assertFalse($this->load([])->getParameter('nowo_wiki.tree.context_menu'));
        self::assertFalse($this->load(['tree' => ['enabled' => true, 'context_menu' => false]])->getParameter('nowo_wiki.tree.context_menu'));
    }

    public function testBadValuesAreRefused(): void
    {
        foreach ([['page_icon' => ['picker' => 'grid']], ['web_ui' => ['page_nav' => 'left']]] as $bad) {
            try {
                (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User'] + $bad]);
                self::fail('Accepted a bad value.');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testThePickerAndEmojiNeedIconSelector(): void
    {
        foreach ([[['picker' => 'icon_selector'], 'page_icon.picker: icon_selector'], [['emoji' => true], 'page_icon.emoji: true']] as [$node, $message]) {
            $config = (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User', 'page_icon' => $node]]);
            try {
                WikiExtension::iconPicker($config, 'App\\Missing\\EmojiCatalogInterface');
                self::fail('Accepted the picker without IconSelector.');
            } catch (LogicException $exception) {
                self::assertStringContainsString('nowo_wiki.' . $message . ' needs nowo-tech/icon-selector-bundle >= 1.6', $exception->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $wiki
     */
    private function load(array $wiki): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User'] + $wiki], $container);

        return $container;
    }
}
