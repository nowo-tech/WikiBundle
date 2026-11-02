<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lets the current browser subscribe to the private topic of a page (1.13): the default
 * {@see MercureWikiCollabSubscriberAuthorizer} sets the `mercureAuthorization` cookie with a subscriber JWT limited to
 * that one topic (no publish right). Called only after the edit permission was checked.
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
