<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\BeaconIdOperations;

/**
 * 1.55: {@see BeaconIdOperationsKernelTest} with `database.private_entity_manager: true`.
 */
final class BeaconIdOperationsPrivateKernelTest extends BeaconIdOperationsKernelTest
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'tenant_paths' => [
            ['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'project_', 'slug_requirement' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?'],
        ],
        'database' => ['private_entity_manager' => true],
    ];
}
