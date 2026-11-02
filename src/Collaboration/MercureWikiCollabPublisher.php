<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * {@see WikiCollabPublisherInterface} on symfony/mercure (1.13): every message is a **private** update, so only
 * subscribers whose JWT lists the page topic receive it.
 */
final readonly class MercureWikiCollabPublisher implements WikiCollabPublisherInterface
{
    /**
     * @param HubInterface $hub the Mercure hub (publisher JWT stays on the server)
     */
    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function publish(string $topic, string $message): void
    {
        $this->hub->publish(new Update($topic, $message, true));
    }
}
