<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Jwt\Grant;

/**
 * {@see WikiCollabSubscriberAuthorizerInterface} on symfony/mercure (1.13): the `mercureAuthorization` cookie (set on
 * the response by symfony/mercure ^0.8 / ^0.9) carries a subscriber grant for this one topic and no publish right;
 * the claims the Mercure protocol 1.0 requires (`iss`, `aud`, `sub`, `client_id`) come from the hub's default claims
 * (symfony/mercure-bundle configuration).
 */
final readonly class MercureWikiCollabSubscriberAuthorizer implements WikiCollabSubscriberAuthorizerInterface
{
    /**
     * @param Authorization $authorization symfony/mercure cookie helper
     * @param string|null $hub hub name (null: the default hub)
     */
    public function __construct(
        private Authorization $authorization,
        private ?string $hub = null,
    ) {
    }

    public function authorize(Request $request, string $topic): void
    {
        $this->authorization->setCookie($request, [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])], null, [], $this->hub);
    }
}
