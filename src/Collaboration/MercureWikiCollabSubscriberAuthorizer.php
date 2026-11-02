<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;

/**
 * {@see WikiCollabSubscriberAuthorizerInterface} on symfony/mercure (1.13): the `mercureAuthorization` cookie (set on
 * the response by symfony/mercure) carries a subscriber JWT for this one topic and no publish right.
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
        $this->authorization->setCookie($request, [$topic], [], [], $this->hub);
    }
}
