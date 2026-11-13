<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;
use function is_string;
use function str_contains;

/**
 * The Doctrine mapping of the wiki entities, prepended to DoctrineBundle's configuration (1.32.0, REQ-ARCH-001 rule 2).
 *
 * - `database.mapping_type: attribute` (default): the ORM attributes, mapped exactly as up to 1.31 (the `WikiBundle`
 *   bundle mapping of the top-level `doctrine.orm`, i.e. the manager named `default`).
 * - `database.mapping_type: xml`: the same tables from `Resources/config/doctrine-xml` (generated from the attributes;
 *   deliberately not `Resources/config/doctrine`, so DoctrineBundle's `auto_mapping` keeps the attributes by default),
 *   mapped on `database.entity_manager`: the top-level `doctrine.orm` for `default`, else
 *   `doctrine.orm.entity_managers.<name>`.
 *
 * Both options are read from the raw `nowo_wiki` configuration before it is processed (prepend runs before load), so
 * they must be literal values in the host configuration; {@see self::check()} fails the build otherwise. No mapping
 * `alias` is set (DoctrineBundle 3 dropped it).
 *
 * @internal
 */
final class WikiDoctrineMapping
{
    /** Namespace of the wiki entities. */
    public const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity';

    /** Name of the DoctrineBundle mapping. */
    public const MAPPING_NAME = 'WikiBundle';

    /** Mapping type the prepend chose (checked against the processed configuration). */
    public const PREPENDED_TYPE_PARAMETER = 'nowo_wiki.internal.prepended_mapping_type';

    /** Entity manager the prepend mapped the XML files on (xml only). */
    public const PREPENDED_MANAGER_PARAMETER = 'nowo_wiki.internal.prepended_mapping_manager';

    /**
     * Prepends the wiki mapping to DoctrineBundle's configuration when DoctrineBundle is registered.
     *
     * @param ContainerBuilder $container container being built
     */
    public static function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        [$type, $manager] = self::rawOptions($container);
        $container->setParameter(self::PREPENDED_TYPE_PARAMETER, $type);
        if ($type !== 'xml') {
            $container->prependExtensionConfig('doctrine', [
                'orm' => ['mappings' => [self::MAPPING_NAME => ['type' => 'attribute', 'is_bundle' => true]]],
            ]);

            return;
        }

        $container->setParameter(self::PREPENDED_MANAGER_PARAMETER, $manager);
        $mappings = ['mappings' => [self::MAPPING_NAME => self::xmlMapping()]];
        $container->prependExtensionConfig('doctrine', [
            'orm' => $manager === 'default' ? $mappings : ['entity_managers' => [$manager => $mappings]],
        ]);
    }

    /**
     * The DoctrineBundle mapping of the XML files.
     *
     * @return array{type: string, is_bundle: false, dir: string, prefix: string}
     */
    public static function xmlMapping(): array
    {
        return ['type' => 'xml', 'is_bundle' => false, 'dir' => self::xmlDirectory(), 'prefix' => self::ENTITY_NAMESPACE];
    }

    /**
     * Directory of the generated XML mapping files.
     */
    public static function xmlDirectory(): string
    {
        return __DIR__ . '/../Resources/config/doctrine-xml';
    }

    /**
     * Fails the build when the processed `database` options differ from what {@see self::prepend()} read (a parameter
     * or an environment variable in `mapping_type`, or in `entity_manager` with the XML mapping).
     *
     * @param ContainerBuilder $container container being built
     * @param array<string, mixed> $database processed `nowo_wiki.database`
     *
     * @throws InvalidConfigurationException when they differ
     */
    public static function check(ContainerBuilder $container, array $database): void
    {
        if (!$container->hasParameter(self::PREPENDED_TYPE_PARAMETER)) {
            return; // DoctrineBundle not registered (or load() without prepend()): nothing was mapped
        }
        $type = $database['mapping_type'] ?? 'attribute';
        if ($container->getParameter(self::PREPENDED_TYPE_PARAMETER) !== $type) {
            throw new InvalidConfigurationException('nowo_wiki.database.mapping_type must be a literal "attribute" or "xml" in the host configuration (config/packages/nowo_wiki.yaml): the entity mapping is decided before the configuration is processed.');
        }
        if ($type === 'xml' && $container->getParameter(self::PREPENDED_MANAGER_PARAMETER) !== ($database['entity_manager'] ?? 'default')) {
            throw new InvalidConfigurationException('With nowo_wiki.database.mapping_type: xml, nowo_wiki.database.entity_manager must be a literal manager name in the host configuration (config/packages/nowo_wiki.yaml): the XML mapping is added to that manager before the configuration is processed.');
        }
    }

    /**
     * The literal `database.mapping_type` and `database.entity_manager` of the raw configuration (the last one set
     * wins, as when processed); anything else counts as the default.
     *
     * @return array{0: string, 1: string}
     */
    private static function rawOptions(ContainerBuilder $container): array
    {
        $type    = 'attribute';
        $manager = 'default';
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $config) {
            $database = is_array($config) ? ($config['database'] ?? null) : null;
            if (!is_array($database)) {
                continue;
            }
            if (is_string($database['mapping_type'] ?? null)) {
                $type = $database['mapping_type'];
            }
            // A parameter or an environment variable keeps the default here; check() then fails the XML build.
            if (is_string($database['entity_manager'] ?? null) && $database['entity_manager'] !== '' && !str_contains($database['entity_manager'], '%')) {
                $manager = $database['entity_manager'];
            }
        }

        return [$type, $manager];
    }
}
