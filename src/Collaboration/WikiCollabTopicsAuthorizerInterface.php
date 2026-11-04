<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Enum\WikiAction;
use Symfony\Component\HttpFoundation\Request;

/**
 * A subscriber authorizer that grants several topics at once (1.16): the co-editing topic of a page and, for viewers
 * with {@see WikiAction::ViewComments}, its private comments topic (`<topic>/comments`), in one
 * subscriber grant (a second cookie would replace the first). The default Mercure authorizer implements it; a host
 * authorizer that only implements {@see WikiCollabSubscriberAuthorizerInterface} keeps working, without live comment
 * updates (the thread component then refreshes on its own actions only).
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
