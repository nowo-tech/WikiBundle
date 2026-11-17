<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\LinkPicker;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\LinkPicker\ConfiguredWikiLinkPickerSettings;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * 1.40 (owner decision O4): the default tenant of a wiki is its `tenant_paths` row; the runtime settings port.
 */
final class WikiLinkPickerPortsTest extends TestCase
{
    public function testTheTenantIsTheFirstMatchingRow(): void
    {
        $paths = [
            ['path' => '/org/{spaceSlug}', 'name_prefix' => 'org_', 'slug_requirement' => 'org-[a-z]+'],
            ['path' => '/p/{spaceSlug}', 'name_prefix' => 'p_', 'slug_requirement' => '[a-z]+(?:-[a-z]+)*'],
        ];
        $resolver = new WikiTenantPathsLinkTenantResolver(new WikiTenantPaths($paths, []));

        self::assertSame('tenant_path:0', $resolver->tenantOf($this->space('org-acme')));
        self::assertSame('tenant_path:1', $resolver->tenantOf($this->space('docs')));
        self::assertSame(WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW, $resolver->tenantOf($this->space('9-digits')));
        self::assertNull((new WikiTenantPathsLinkTenantResolver(new WikiTenantPaths($paths, [], true)))->tenantOf($this->space('9-digits')));
        self::assertSame(WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW, (new WikiTenantPathsLinkTenantResolver())->tenantOf($this->space('docs')));
    }

    public function testTheConfiguredSettings(): void
    {
        self::assertTrue((new ConfiguredWikiLinkPickerSettings())->crossWiki());
        self::assertFalse((new ConfiguredWikiLinkPickerSettings(false))->crossWiki());
    }

    private function space(string $slug): WikiSpaceSnapshot
    {
        return WikiSnapshots::space(new WikiSpace($slug, 'S', WikiSpaceOwnerScope::Team, 't'));
    }
}
