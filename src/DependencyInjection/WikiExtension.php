<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Ai\WikiContextRetriever;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function rtrim;
use function sprintf;

/**
 * Loads bundle configuration and registers services.
 */
final class WikiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $prefix         = rtrim((string) $config['table_prefix'], '_');
        $spacesTable    = $prefix . '_spaces';
        $pagesTable     = $prefix . '_pages';
        $revisionsTable = $prefix . '_page_revisions';
        $database       = $config['database'];
        $emName         = (string) $database['entity_manager'];

        $container->setParameter('nowo_wiki.user_class', $config['user_class']);
        $container->setParameter('nowo_wiki.spaces_table', $spacesTable);
        $container->setParameter('nowo_wiki.pages_table', $pagesTable);
        $container->setParameter('nowo_wiki.revisions_table', $revisionsTable);
        $container->setParameter('nowo_wiki.database', $database);
        $container->setParameter('nowo_wiki.space_scope', $config['space_scope']);
        $container->setParameter('nowo_wiki.route_prefix', $config['route_prefix']);
        $container->setParameter('nowo_wiki.dashboard_route', $config['dashboard_route']);
        $container->setParameter('nowo_wiki.routes', $config['routes']);
        $container->setParameter('nowo_wiki.templates', $config['templates']);
        $container->setParameter('nowo_wiki.firewall', $config['firewall']);
        $container->setParameter('nowo_wiki.editor', $config['editor']);
        $container->setParameter('nowo_wiki.ai', $config['ai']);
        $container->setParameter('nowo_wiki.import_export', $config['import_export']);
        $container->setParameter('nowo_wiki.import_export.enabled', (bool) $config['import_export']['enabled']);

        $teamResolverId = $config['team_membership_resolver'] ?? null;
        if (!is_string($teamResolverId) || $teamResolverId === '') {
            $teamResolverId = NullWikiTeamMembershipResolver::class;
            $container->setDefinition(NullWikiTeamMembershipResolver::class, new Definition(NullWikiTeamMembershipResolver::class));
        }
        $container->setAlias(WikiTeamMembershipResolverInterface::class, $teamResolverId);

        $accessCheckerId = $config['security']['access_checker'] ?? null;
        if (!is_string($accessCheckerId) || $accessCheckerId === '') {
            $accessCheckerId = 'nowo_wiki.access_checker.default';
            $security        = $config['security'];
            $container->setDefinition($accessCheckerId, (new Definition(ConfigurableWikiAccessChecker::class))
                ->setAutowired(true)
                ->setArgument('$adminRoles', $security['admin_roles'])
                ->setArgument('$accessRoles', $security['access_roles'])
                ->setArgument('$listRoles', $security['list_roles'])
                ->setArgument('$createRoles', $security['create_roles'])
                ->setArgument('$editRoles', $security['edit_roles'])
                ->setArgument('$historyRoles', $security['history_roles'])
                ->setArgument('$archiveRoles', $security['archive_roles'])
                ->setArgument('$aiRoles', $security['ai_roles'])
                ->setArgument('$importRoles', $security['import_roles'])
                ->setArgument('$exportRoles', $security['export_roles']));
        }
        $container->setAlias(WikiAccessCheckerInterface::class, $accessCheckerId);
        $container->setAlias(WikiHtmlSanitizerInterface::class, WikiHtmlSanitizer::class);
        $container->setDefinition(WikiHtmlSanitizer::class, new Definition(WikiHtmlSanitizer::class));

        $emRef = new Reference(sprintf('doctrine.orm.%s_entity_manager', $emName));

        foreach ([
            DoctrineOrmWikiSpaceRepository::class        => WikiSpaceRepositoryInterface::class,
            DoctrineOrmWikiPageRepository::class         => WikiPageRepositoryInterface::class,
            DoctrineOrmWikiPageRevisionRepository::class => WikiPageRevisionRepositoryInterface::class,
        ] as $repoClass => $interface) {
            $container->setDefinition($repoClass, (new Definition($repoClass))
                ->setAutowired(false)
                ->setArgument('$entityManager', $emRef));
            $container->setAlias($interface, $repoClass);
        }

        $container->setDefinition(WikiMetadataListener::class, (new Definition(WikiMetadataListener::class))
            ->setArgument('$spacesTableName', $spacesTable)
            ->setArgument('$pagesTableName', $pagesTable)
            ->setArgument('$revisionsTableName', $revisionsTable)
            ->setArgument('$userClass', $config['user_class'])
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']));

        $container->setDefinition(WikiSpaceAccessResolver::class, (new Definition(WikiSpaceAccessResolver::class))
            ->setAutowired(true)
            ->setArgument('$defaultOwnerScope', $config['space_scope']));
        $container->setAlias(WikiSpaceAccessResolverInterface::class, WikiSpaceAccessResolver::class);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->registerAiServices($container, $config['ai']);
    }

    /**
     * @param array{enabled: bool, agent: string, context_injection: bool, max_context_pages: int, max_context_chars: int, system_prompt: ?string} $aiConfig
     */
    private function registerAiServices(ContainerBuilder $container, array $aiConfig): void
    {
        $container->setDefinition(NullWikiAiAssistant::class, new Definition(NullWikiAiAssistant::class));

        if (!$aiConfig['enabled']) {
            $container->setAlias(WikiAiAssistantInterface::class, NullWikiAiAssistant::class);

            return;
        }

        if (!interface_exists(AgentInterface::class)) {
            // @codeCoverageIgnoreStart
            throw new LogicException('nowo_wiki.ai.enabled is true but symfony/ai-bundle is not installed. Run: composer require symfony/ai-bundle');
            // @codeCoverageIgnoreEnd
        }

        $agentName = $aiConfig['agent'];
        $agentId   = str_starts_with($agentName, 'ai.agent.') ? $agentName : 'ai.agent.' . $agentName;

        $container->setDefinition(WikiContextRetriever::class, (new Definition(WikiContextRetriever::class))->setAutowired(true));
        $container->setDefinition(WikiKnowledgeSearchTool::class, (new Definition(WikiKnowledgeSearchTool::class))->setAutowired(true));
        $container->setDefinition(SymfonyAiWikiAssistant::class, (new Definition(SymfonyAiWikiAssistant::class))
            ->setAutowired(false)
            ->setArgument('$agent', new Reference($agentId))
            ->setArgument('$contextRetriever', new Reference(WikiContextRetriever::class))
            ->setArgument('$useContextInjection', $aiConfig['context_injection'])
            ->setArgument('$maxContextPages', $aiConfig['max_context_pages'])
            ->setArgument('$maxContextChars', $aiConfig['max_context_chars'])
            ->setArgument('$systemPrompt', $aiConfig['system_prompt']));
        $container->setAlias(WikiAiAssistantInterface::class, SymfonyAiWikiAssistant::class);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        'nowo_wiki' => [
                            'base_path' => '/bundles/wiki',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'WikiBundle' => [
                            'type'      => 'attribute',
                            'is_bundle' => true,
                        ],
                    ],
                ],
            ]);
        }
    }
}
