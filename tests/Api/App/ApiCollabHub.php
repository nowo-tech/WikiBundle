<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A hub that records what it is told (no Mercure in the tests).
 */
final class ApiCollabHub implements WikiCollabPublisherInterface, WikiCollabSubscriberAuthorizerInterface
{
    /** @var list<string> */
    public static array $published = [];

    /** The hub is down (audit IF-L6). */
    public static bool $down = false;

    public function publish(string $topic, string $message): void
    {
        if (self::$down) {
            throw new RuntimeException('Hub unreachable.');
        }
        self::$published[] = $message;
    }

    public function authorize(Request $request, string $topic): void
    {
    }
}
