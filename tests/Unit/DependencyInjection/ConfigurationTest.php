<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

use function implode;
use function json_encode;

use const E_USER_DEPRECATED;

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
        self::assertNull($config['security']['import_roles']);
        self::assertNull($config['security']['roles']['import']);
        self::assertSame(['ROLE_ADMIN'], Configuration::resolveRoles($config['security'])['import']);
        self::assertSame(Configuration::DEFAULT_ROLES, Configuration::resolveRoles($config['security']));
        self::assertSame(['ROLE_USER'], $config['security']['access_roles']);
        self::assertNull($config['web_ui']['css_framework']);
        self::assertNull($config['web_ui']['layout_template']);
        self::assertNull($config['web_ui']['path_prefix']);
        self::assertNull($config['web_ui']['templates']['index']);
        self::assertNull($config['templates']['layout']);
        self::assertNull($config['route_prefix']);
        self::assertTrue($config['web_ui']['enabled']);
        self::assertSame(['spaces_per_page' => 24, 'revisions_per_page' => 25, 'max_tree_pages' => 500], $config['pagination']);
        self::assertSame(WikiManageController::DEFAULT_PAGINATION, $config['pagination']);
    }

    public function testPaginationRejectsZeroPageSize(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'user_class' => 'App\\Entity\\User',
            'pagination' => ['spaces_per_page' => 0],
        ]]);
    }

    public function testDeadKeysAreDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new Processor())->processConfiguration(new Configuration(), [[
                'user_class' => 'App\\Entity\\User',
                'database'   => ['driver' => 'doctrine_orm', 'platform' => 'postgresql', 'entity_manager' => 'wiki'],
                'firewall'   => 'admin',
            ]]);
        } finally {
            restore_error_handler();
        }

        self::assertCount(3, $deprecations);
        self::assertStringContainsString('nowo_wiki.database.driver', $deprecations[0] . $deprecations[1] . $deprecations[2]);
        self::assertStringContainsString('nowo_wiki.database.platform', $deprecations[0] . $deprecations[1] . $deprecations[2]);
        self::assertStringContainsString('nowo_wiki.firewall', $deprecations[0] . $deprecations[1] . $deprecations[2]);
    }

    public function testDefaultsDoNotTriggerDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User']]);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function testRolesPerActionWinOverTheDeprecatedKeys(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $config = (new Processor())->processConfiguration(new Configuration(), [[
                'user_class' => 'App\\Entity\\User',
                'security'   => [
                    'list_roles' => ['ROLE_READER'],
                    'edit_roles' => ['ROLE_WRITER'],
                    'roles'      => ['edit' => ['ROLE_EDITOR'], 'export' => []],
                ],
            ]]);
        } finally {
            restore_error_handler();
        }

        $roles = Configuration::resolveRoles($config['security']);
        self::assertSame(['ROLE_READER'], $roles['list']);
        self::assertSame(['ROLE_EDITOR'], $roles['edit']);
        self::assertSame([], $roles['export']);
        self::assertSame(['ROLE_ADMIN'], $roles['admin']);
        self::assertCount(2, $deprecations);
        self::assertStringContainsString('security.roles.list', $deprecations[0] . $deprecations[1]);
    }

    public function testAnExplicitNullRoleListGrantsNothingLikeAnEmptyOne(): void
    {
        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);

        try {
            foreach ([null, []] as $value) {
                $config = (new Processor())->processConfiguration(new Configuration(), [[
                    'user_class' => 'App\\Entity\\User',
                    'security'   => [
                        'create_roles' => $value,
                        'admin_roles'  => $value,
                        'roles'        => ['import' => $value, 'export' => $value],
                    ],
                ]]);
                $roles = Configuration::resolveRoles($config['security']);

                self::assertSame([], $roles['create'], 'deprecated create_roles: ' . json_encode($value));
                self::assertSame([], $roles['admin']);
                self::assertSame([], $roles['import'], 'roles.import: ' . json_encode($value));
                self::assertSame([], $roles['export']);
                self::assertSame(['ROLE_USER'], $roles['edit'], 'an absent key keeps the default');
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testResolveRolesDropsBlankRoles(): void
    {
        self::assertSame(['ROLE_A'], Configuration::resolveRoles(['roles' => ['list' => ['ROLE_A', '', 'x' => 3]]])['list']);
        self::assertSame(['ROLE_B'], Configuration::resolveRoles(['list_roles' => 'ROLE_B'])['list']);
    }

    public function testRolesMustBeListsOfNames(): void
    {
        self::assertTrue(Configuration::isRoleList(['ROLE_A']));
        self::assertFalse(Configuration::isRoleList('ROLE_A'));
        self::assertFalse(Configuration::isRoleList([['ROLE_A']]));

        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'user_class' => 'App\\Entity\\User',
            'security'   => ['roles' => ['list' => 'ROLE_A']],
        ]]);
    }

    public function testDeprecatedRoleKeysAreValidated(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);

        try {
            (new Processor())->processConfiguration(new Configuration(), [[
                'user_class' => 'App\\Entity\\User',
                'security'   => ['list_roles' => 'ROLE_A'],
            ]]);
        } finally {
            restore_error_handler();
        }
    }

    public function testDeprecatedWebUiKeysTriggerDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new Processor())->processConfiguration(new Configuration(), [[
                'user_class'   => 'App\\Entity\\User',
                'route_prefix' => '/admin',
                'templates'    => ['layout' => 'base.html.twig', 'index' => 'wiki/index.html.twig'],
                'web_ui'       => ['css_framework' => 'tailwind'],
            ]]);
        } finally {
            restore_error_handler();
        }

        $all = implode("\n", $deprecations);
        self::assertCount(4, $deprecations);
        self::assertStringContainsString('web_ui.path_prefix', $all);
        self::assertStringContainsString('web_ui.layout_template', $all);
        self::assertStringContainsString('web_ui.templates.index', $all);
        self::assertStringContainsString('nowo_ui_kit.css_framework', $all);
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
