<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Collaboration\WikiCollabCookie;
use Nowo\WikiBundle\Collaboration\WikiCollabCookieListener;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriptionPolicyInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabTopicsAuthorizerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Asks a framework-free {@see WikiCollabSubscriptionPolicyInterface} (1.34.0) and queues the cookies it grants for
 * the response of that request ({@see WikiCollabCookieListener}).
 *
 * @internal
 */
final readonly class WikiCollabSubscriptionGate implements WikiCollabTopicsAuthorizerInterface
{
    public function __construct(private WikiCollabSubscriptionPolicyInterface $policy)
    {
    }

    public function authorize(Request $request, string $topic): void
    {
        $this->authorizeTopics($request, [$topic]);
    }

    public function authorizeTopics(Request $request, array $topics): void
    {
        $cookies = [];
        foreach ($this->policy->grant(WikiRequestContexts::fromRequest($request), $topics) as $cookie) {
            if ($cookie instanceof WikiCollabCookie) {
                $cookies[] = $cookie;
            }
        }
        WikiCollabCookieListener::queue($request, $cookies);
    }
}
