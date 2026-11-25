<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use LogicException;
use Nowo\WikiBundle\Ai\WikiAiAnswererInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanPolicyInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Avatar\WikiUserAvatarSourceInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriptionPolicyInterface;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiPortsPass;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Panel\WikiPanelStatesInterface;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiAiAssistantInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiFilesInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Port\Internal\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Port\Internal\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Port\Internal\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Port\Internal\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Port\Internal\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipSourceInterface;
use Nowo\WikiBundle\Service\WikiSpaceIdLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\Tree\WikiTreeStatesInterface;
use Throwable;

use function array_values;
use function class_exists;
use function get_debug_type;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * The host ports (REQ-ARCH-001 rules 1 and 3): since 2.0 a host implements only the framework-free twin (1.36), asked
 * through an internal gate (`Port\*Gate`) under the bundle-side interface id (`Port\Internal\*`). The 1.x host
 * interfaces of the same names (deprecated since 1.36, notices since 1.49) are removed. {@see WikiPortsPass} wires them
 * at build time; {@see self::port()} is the factory for services whose class the build cannot know.
 *
 * @internal
 */
final class WikiPorts
{
    /**
     * Port name => [bundle-side interface (also the service id the bundle asks), twin interface, gate class, the gate's
     * other constructor services, the configuration that names the service, the removed 1.x host interface].
     *
     * @var array<string, array{class-string, class-string, class-string, list<string>, string, string}>
     */
    public const PORTS = [
        'access_checker'               => [WikiAccessCheckerInterface::class, WikiAccessPolicyInterface::class, WikiAccessPolicyGate::class, [], 'nowo_wiki.security.access_checker', 'Nowo\\WikiBundle\\Security\\WikiAccessCheckerInterface'],
        'space_access_checker'         => [WikiSpaceAccessCheckerInterface::class, WikiSpaceAccessPolicyInterface::class, WikiSpaceAccessPolicyGate::class, [], 'the WikiSpaceAccessPolicyInterface alias', 'Nowo\\WikiBundle\\Security\\WikiSpaceAccessCheckerInterface'],
        'team_membership_resolver'     => [WikiTeamMembershipResolverInterface::class, WikiTeamMembershipSourceInterface::class, WikiTeamMembershipSourceGate::class, [], 'nowo_wiki.team_membership_resolver', 'Nowo\\WikiBundle\\Security\\WikiTeamMembershipResolverInterface'],
        'space_access_resolver'        => [WikiSpaceAccessResolverInterface::class, WikiSpaceReachInterface::class, WikiSpaceReachGate::class, [WikiSpaceRepositoryInterface::class], 'the WikiSpaceReachInterface alias', 'Nowo\\WikiBundle\\Service\\WikiSpaceAccessResolverInterface'],
        'space_locator'                => [WikiSpaceLocatorInterface::class, WikiSpaceIdLocatorInterface::class, WikiSpaceIdLocatorGate::class, [WikiSpaceRepositoryInterface::class], 'the WikiSpaceIdLocatorInterface alias', 'Nowo\\WikiBundle\\Service\\WikiSpaceLocatorInterface'],
        'avatar_resolver'              => [WikiUserAvatarResolverInterface::class, WikiUserAvatarSourceInterface::class, WikiUserAvatarSourceGate::class, [], 'the WikiUserAvatarSourceInterface alias', 'Nowo\\WikiBundle\\Avatar\\WikiUserAvatarResolverInterface'],
        'panel_state_store'            => [WikiPanelStateStoreInterface::class, WikiPanelStatesInterface::class, WikiPanelStatesGate::class, [], 'nowo_wiki.comments.state_store', 'Nowo\\WikiBundle\\Panel\\WikiPanelStateStoreInterface'],
        'tree_state_store'             => [WikiTreeStateStoreInterface::class, WikiTreeStatesInterface::class, WikiTreeStatesGate::class, [], 'nowo_wiki.tree.state_store', 'Nowo\\WikiBundle\\Tree\\WikiTreeStateStoreInterface'],
        'collab_subscriber_authorizer' => [WikiCollabSubscriberAuthorizerInterface::class, WikiCollabSubscriptionPolicyInterface::class, WikiCollabSubscriptionGate::class, [], 'nowo_wiki.collaboration.subscriber_authorizer', 'Nowo\\WikiBundle\\Collaboration\\WikiCollabSubscriberAuthorizerInterface'],
        'api_authenticator'            => [WikiApiAuthenticatorInterface::class, WikiApiRequestAuthenticatorInterface::class, WikiApiRequestAuthenticatorGate::class, [], 'nowo_wiki.api.hooks.authenticator', 'Nowo\\WikiBundle\\Api\\Hook\\WikiApiAuthenticatorInterface'],
        'api_files'                    => [WikiApiFilesInterface::class, WikiApiFileStoreInterface::class, WikiApiFileStoreGate::class, [], 'nowo_wiki.api.hooks.files', 'Nowo\\WikiBundle\\Api\\Hook\\WikiApiFilesInterface'],
        'api_plan_gate'                => [WikiApiPlanGateInterface::class, WikiApiPlanPolicyInterface::class, WikiApiPlanPolicyGate::class, [], 'nowo_wiki.api.hooks.plan_gate', 'Nowo\\WikiBundle\\Api\\Hook\\WikiApiPlanGateInterface'],
        'api_directory'                => [WikiApiDirectoryInterface::class, WikiApiWikiDirectoryInterface::class, WikiApiWikiDirectoryGate::class, [WikiSpaceRepositoryInterface::class], 'nowo_wiki.api.hooks.directory', 'Nowo\\WikiBundle\\Api\\Hook\\WikiApiDirectoryInterface'],
        'ai_assistant'                 => [WikiAiAssistantInterface::class, WikiAiAnswererInterface::class, WikiAiAnswererGate::class, [WikiPageRepositoryInterface::class, WikiSpaceAccessResolverInterface::class], 'the WikiAiAnswererInterface alias', 'Nowo\\WikiBundle\\Ai\\WikiAiAssistantInterface'],
        'attachment_limits'            => [WikiAttachmentLimitsProviderInterface::class, WikiAttachmentLimitsPolicyInterface::class, WikiAttachmentLimitsPolicyGate::class, [], 'the WikiAttachmentLimitsPolicyInterface alias', 'Nowo\\WikiBundle\\Attachment\\WikiAttachmentLimitsProviderInterface'],
    ];

    /** 2.0: the removed 1.x object link interface (tag `nowo_wiki.object_link_resolver`) and its twin. */
    public const REMOVED_OBJECT_LINK_RESOLVER = 'Nowo\\WikiBundle\\Link\\WikiObjectLinkResolverInterface';

    /** 2.0: the removed 1.x sub-interface of the co-editing subscriber authorizer. */
    public const REMOVED_COLLAB_TOPICS_AUTHORIZER = 'Nowo\\WikiBundle\\Collaboration\\WikiCollabTopicsAuthorizerInterface';

    /**
     * The removed 1.x host interfaces (FQCN => the twin to implement instead).
     *
     * @return array<string, string>
     */
    public static function removed(): array
    {
        $removed = [];
        foreach (self::PORTS as [, $twin, , , , $legacy]) {
            $removed[$legacy] = $twin;
        }
        $removed[self::REMOVED_OBJECT_LINK_RESOLVER]     = WikiObjectLinkSourceInterface::class;
        $removed[self::REMOVED_COLLAB_TOPICS_AUTHORIZER] = WikiCollabSubscriptionPolicyInterface::class;

        return $removed;
    }

    /**
     * The service to ask for a port: a bundle service implementing the bundle-side interface itself, else the gate
     * around a host twin.
     *
     * @param string $name port name ({@see self::PORTS})
     * @param mixed $service the configured service
     * @param object ...$dependencies the gate's other constructor services (in {@see self::PORTS} order)
     *
     * @throws LogicException when the port is unknown or the service does not implement the twin (fail closed)
     *
     * @return object the bundle-side interface's implementation to ask
     */
    public static function port(string $name, mixed $service, object ...$dependencies): object
    {
        if (!isset(self::PORTS[$name])) {
            throw new LogicException(sprintf('Unknown WikiBundle port "%s".', $name));
        }

        [$internal, $twin, , , $option] = self::PORTS[$name];
        if ($service instanceof $internal && self::isBundleClass($service::class)) {
            return $service;
        }
        $gate = self::gate($name, $service, array_values($dependencies));
        if ($gate !== null) {
            return $gate;
        }

        throw new LogicException(sprintf('The service "%s" of %s must implement "%s" (2.0 removed the 1.x interface it may still implement; see UPGRADING "To 2.0.0").', get_debug_type($service), $option, $twin));
    }

    /**
     * Whether a class is one of the bundle's own (its defaults and its test suite): only those may implement the
     * bundle-side interfaces directly.
     */
    public static function isBundleClass(string $class): bool
    {
        return str_starts_with($class, 'Nowo\\WikiBundle\\');
    }

    /**
     * 2.0: whether a class can be loaded; a class that still implements a removed 1.x interface fails with the
     * migration message instead of a PHP error.
     *
     * @param string $owner what names the class (a service id, a configuration key)
     *
     * @throws LogicException when the class implements a removed 1.x host interface
     */
    public static function loadable(string $class, string $owner): bool
    {
        try {
            return class_exists($class);
        } catch (Throwable $error) {
            foreach (self::removed() as $legacy => $twin) {
                if (str_contains($error->getMessage(), '"' . $legacy . '"')) {
                    throw new LogicException(sprintf('nowo_wiki: %s (%s) implements %s, which 2.0 removed (deprecated since 1.36, notices since 1.49): implement %s instead. See UPGRADING "To 2.0.0".', $owner, $class, $legacy, $twin), 0, $error);
                }
            }

            throw $error;
        }
    }

    /**
     * @param list<object> $dependencies
     */
    private static function gate(string $name, mixed $service, array $dependencies): ?object
    {
        $first  = $dependencies[0] ?? null;
        $second = $dependencies[1] ?? null;

        return match (true) {
            $name === 'access_checker' && $service instanceof WikiAccessPolicyInterface                                                                                                     => new WikiAccessPolicyGate($service),
            $name === 'space_access_checker' && $service instanceof WikiSpaceAccessPolicyInterface                                                                                          => new WikiSpaceAccessPolicyGate($service),
            $name === 'team_membership_resolver' && $service instanceof WikiTeamMembershipSourceInterface                                                                                   => new WikiTeamMembershipSourceGate($service),
            $name === 'space_access_resolver' && $service instanceof WikiSpaceReachInterface && $first instanceof WikiSpaceRepositoryInterface                                              => new WikiSpaceReachGate($service, $first),
            $name === 'space_locator' && $service instanceof WikiSpaceIdLocatorInterface && $first instanceof WikiSpaceRepositoryInterface                                                  => new WikiSpaceIdLocatorGate($service, $first),
            $name === 'avatar_resolver' && $service instanceof WikiUserAvatarSourceInterface                                                                                                => new WikiUserAvatarSourceGate($service),
            $name === 'panel_state_store' && $service instanceof WikiPanelStatesInterface                                                                                                   => new WikiPanelStatesGate($service),
            $name === 'tree_state_store' && $service instanceof WikiTreeStatesInterface                                                                                                     => new WikiTreeStatesGate($service),
            $name === 'collab_subscriber_authorizer' && $service instanceof WikiCollabSubscriptionPolicyInterface                                                                           => new WikiCollabSubscriptionGate($service),
            $name === 'api_authenticator' && $service instanceof WikiApiRequestAuthenticatorInterface                                                                                       => new WikiApiRequestAuthenticatorGate($service),
            $name === 'api_files' && $service instanceof WikiApiFileStoreInterface                                                                                                          => new WikiApiFileStoreGate($service),
            $name === 'api_plan_gate' && $service instanceof WikiApiPlanPolicyInterface                                                                                                     => new WikiApiPlanPolicyGate($service),
            $name === 'api_directory' && $service instanceof WikiApiWikiDirectoryInterface && $first instanceof WikiSpaceRepositoryInterface                                                => new WikiApiWikiDirectoryGate($service, $first),
            $name === 'ai_assistant' && $service instanceof WikiAiAnswererInterface && $first instanceof WikiPageRepositoryInterface && $second instanceof WikiSpaceAccessResolverInterface => new WikiAiAnswererGate($service, $first, $second),
            $name === 'attachment_limits' && $service instanceof WikiAttachmentLimitsPolicyInterface                                                                                        => new WikiAttachmentLimitsPolicyGate($service),
            default                                                                                                                                                                         => null,
        };
    }
}
