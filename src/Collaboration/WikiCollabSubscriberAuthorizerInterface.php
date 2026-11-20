<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lets the current browser subscribe to the private topic of a page (1.13): the default
 * {@see MercureWikiCollabSubscriberAuthorizer} sets the `mercureAuthorization` cookie with a subscriber JWT limited to
 * that one topic (no publish right). Called only after the edit permission was checked.
 *
 * @deprecated since 1.36, removed in 2.0: implement {@see WikiCollabSubscriptionPolicyInterface} instead
 *             (1.49: a host service that still implements it triggers a deprecation notice)
 */
interface WikiCollabSubscriberAuthorizerInterface
{
    /**
     * Authorizes the request's browser to subscribe to the topic.
     *
     * @param Request $request current request (the cookie is attached to its response)
     * @param string $topic topic IRI of the page
     */
    public function authorize(Request $request, string $topic): void;
}
