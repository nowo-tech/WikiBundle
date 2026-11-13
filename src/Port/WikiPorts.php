<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use LogicException;
use Nowo\WikiBundle\Ai\WikiAiAnswererInterface;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiFilesInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanPolicyInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Avatar\WikiUserAvatarSourceInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriptionPolicyInterface;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiPortsPass;
use Nowo\WikiBundle\Panel\WikiPanelStatesInterface;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipSourceInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceIdLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\Tree\WikiTreeStatesInterface;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;

use function array_values;
use function get_debug_type;
use function sprintf;

/**
 * The host ports that have a framework-free twin (1.34.0, REQ-ARCH-001 rules 1 and 3) and their selection rule: a
 * service implementing the deprecated interface is asked through it exactly as before (also when it implements the
 * twin too); a service implementing only the twin is asked through its gate. {@see WikiPortsPass} wires them at build
 * time; {@see self::port()} is the factory for services whose class the build cannot know.
 *
 * @internal
 */
final class WikiPorts
{
    /**
     * Port name => [deprecated interface (also the service id the bundle asks), twin interface, gate class, the gate's
     * other constructor services, the configuration that names the service].
     *
     * @var array<string, array{class-string, class-string, class-string, list<string>, string}>
     */
    public const PORTS = [
        'access_checker'               => [WikiAccessCheckerInterface::class, WikiAccessPolicyInterface::class, WikiAccessPolicyGate::class, [], 'nowo_wiki.security.access_checker'],
        'space_access_checker'         => [WikiSpaceAccessCheckerInterface::class, WikiSpaceAccessPolicyInterface::class, WikiSpaceAccessPolicyGate::class, [], 'the WikiSpaceAccessCheckerInterface alias'],
        'team_membership_resolver'     => [WikiTeamMembershipResolverInterface::class, WikiTeamMembershipSourceInterface::class, WikiTeamMembershipSourceGate::class, [], 'nowo_wiki.team_membership_resolver'],
        'space_access_resolver'        => [WikiSpaceAccessResolverInterface::class, WikiSpaceReachInterface::class, WikiSpaceReachGate::class, [WikiSpaceRepositoryInterface::class], 'the WikiSpaceAccessResolverInterface alias'],
        'space_locator'                => [WikiSpaceLocatorInterface::class, WikiSpaceIdLocatorInterface::class, WikiSpaceIdLocatorGate::class, [WikiSpaceRepositoryInterface::class], 'the WikiSpaceLocatorInterface alias'],
        'avatar_resolver'              => [WikiUserAvatarResolverInterface::class, WikiUserAvatarSourceInterface::class, WikiUserAvatarSourceGate::class, [], 'the WikiUserAvatarResolverInterface alias'],
        'panel_state_store'            => [WikiPanelStateStoreInterface::class, WikiPanelStatesInterface::class, WikiPanelStatesGate::class, [], 'nowo_wiki.comments.state_store'],
        'tree_state_store'             => [WikiTreeStateStoreInterface::class, WikiTreeStatesInterface::class, WikiTreeStatesGate::class, [], 'nowo_wiki.tree.state_store'],
        'collab_subscriber_authorizer' => [WikiCollabSubscriberAuthorizerInterface::class, WikiCollabSubscriptionPolicyInterface::class, WikiCollabSubscriptionGate::class, [], 'nowo_wiki.collaboration.subscriber_authorizer'],
        'api_authenticator'            => [WikiApiAuthenticatorInterface::class, WikiApiRequestAuthenticatorInterface::class, WikiApiRequestAuthenticatorGate::class, [], 'nowo_wiki.api.hooks.authenticator'],
        'api_files'                    => [WikiApiFilesInterface::class, WikiApiFileStoreInterface::class, WikiApiFileStoreGate::class, [], 'nowo_wiki.api.hooks.files'],
        'api_plan_gate'                => [WikiApiPlanGateInterface::class, WikiApiPlanPolicyInterface::class, WikiApiPlanPolicyGate::class, [], 'nowo_wiki.api.hooks.plan_gate'],
        'api_directory'                => [WikiApiDirectoryInterface::class, WikiApiWikiDirectoryInterface::class, WikiApiWikiDirectoryGate::class, [WikiSpaceRepositoryInterface::class], 'nowo_wiki.api.hooks.directory'],
        'ai_assistant'                 => [WikiAiAssistantInterface::class, WikiAiAnswererInterface::class, WikiAiAnswererGate::class, [WikiPageRepositoryInterface::class, WikiSpaceAccessResolverInterface::class], 'the WikiAiAssistantInterface alias'],
        'attachment_limits'            => [WikiAttachmentLimitsProviderInterface::class, WikiAttachmentLimitsPolicyInterface::class, WikiAttachmentLimitsPolicyGate::class, [], 'the WikiAttachmentLimitsProviderInterface alias'],
    ];

    /**
     * The service to ask for a port: the service itself when it implements the deprecated interface, else its gate.
     *
     * @param string $name port name ({@see self::PORTS})
     * @param mixed $service the configured service
     * @param object ...$dependencies the gate's other constructor services (in {@see self::PORTS} order)
     *
     * @throws LogicException when the port is unknown or the service implements neither interface (fail closed)
     *
     * @return object the deprecated interface's implementation to ask
     */
    public static function port(string $name, mixed $service, object ...$dependencies): object
    {
        if (!isset(self::PORTS[$name])) {
            throw new LogicException(sprintf('Unknown WikiBundle port "%s".', $name));
        }

        [$legacy, $twin, , , $option] = self::PORTS[$name];
        if ($service instanceof $legacy) {
            return $service;
        }
        $gate = self::gate($name, $service, array_values($dependencies));
        if ($gate !== null) {
            return $gate;
        }

        throw new LogicException(sprintf('The service "%s" of %s must implement "%s" (or the deprecated "%s").', get_debug_type($service), $option, $twin, $legacy));
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
