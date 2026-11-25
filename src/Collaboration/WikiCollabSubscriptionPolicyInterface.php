<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Port\Internal\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Port\Internal\WikiCollabTopicsAuthorizerInterface;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;

/**
 * Lets a browser subscribe to co-editing topics, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiCollabSubscriberAuthorizerInterface` / `WikiCollabTopicsAuthorizerInterface`
 * (`nowo_wiki.collaboration.subscriber_authorizer` names your service). Return the cookies to set (e.g. the hub's
 * subscriber JWT); the bundle adds them to the response of that request.
 */
interface WikiCollabSubscriptionPolicyInterface
{
    /**
     * @param WikiRequestContext $request the request (no credential header)
     * @param list<string> $topics topics to subscribe to (and publish to none)
     *
     * @return list<WikiCollabCookie> cookies to set
     */
    public function grant(WikiRequestContext $request, array $topics): array;
}
