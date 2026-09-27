<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'user_class' => 'App\\Entity\\User',
        ]]);

        self::assertSame('wiki_', $config['table_prefix']);
        self::assertSame('notion', $config['editor']['tiptap_config']);
        self::assertSame('nowo_wiki_index', $config['routes']['index']['name']);
        self::assertFalse($config['ai']['enabled']);
        self::assertSame('wiki_assistant', $config['ai']['agent']);
        self::assertSame(30, $config['ai']['http_timeout']);
        self::assertTrue($config['import_export']['enabled']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['import_roles']);
        self::assertSame('tabler', $config['web_ui']['css_framework']);
        self::assertTrue($config['web_ui']['enabled']);
    }

    public function testAiHttpTimeoutMustBePositive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['http_timeout' => 0],
        ]]);
    }
}
