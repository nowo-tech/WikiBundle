<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A hub that records what it is told (no Mercure in the tests).
 */
final class ApiCollabHub implements WikiCollabPublisherInterface, WikiCollabSubscriberAuthorizerInterface
{
    /** @var list<string> */
    public static array $published = [];

    public function publish(string $topic, string $message): void
    {
        self::$published[] = $message;
    }

    public function authorize(Request $request, string $topic): void
    {
    }
}
