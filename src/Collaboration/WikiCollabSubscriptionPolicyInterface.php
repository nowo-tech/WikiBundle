<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\ValueObject\WikiRequestContext;

/**
 * Lets a browser subscribe to co-editing topics, framework-free (1.34.0): the twin of the deprecated
 * {@see WikiCollabSubscriberAuthorizerInterface} / {@see WikiCollabTopicsAuthorizerInterface}
 * (`nowo_wiki.collaboration.subscriber_authorizer` takes either). Return the cookies to set (e.g. the hub's
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
