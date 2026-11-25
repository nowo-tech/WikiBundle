<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port\Internal;

use Nowo\WikiBundle\Collaboration\WikiCollabSubscriptionPolicyInterface;
use Nowo\WikiBundle\Enum\WikiAction;
use Symfony\Component\HttpFoundation\Request;

/**
 * A subscriber authorizer that grants several topics at once (1.16): the co-editing topic of a page and, for viewers
 * with {@see WikiAction::ViewComments}, its private comments topic (`<topic>/comments`), in one
 * subscriber grant (a second cookie would replace the first). The default Mercure authorizer and the gate of a host
 * {@see WikiCollabSubscriptionPolicyInterface} implement it.
 *
 * @internal Bundle side of a host port since 2.0 (the 1.x host interface of this name was removed): a host implements
 *           {@see WikiCollabSubscriptionPolicyInterface} instead.
 */
interface WikiCollabTopicsAuthorizerInterface extends WikiCollabSubscriberAuthorizerInterface
{
    /**
     * Lets the current browser subscribe to every topic (and publish to none).
     *
     * @param list<string> $topics
     */
    public function authorizeTopics(Request $request, array $topics): void;
}
