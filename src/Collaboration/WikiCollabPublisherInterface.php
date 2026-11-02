<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * Relays a collaboration message to the browsers of a page (1.13). The default is {@see MercureWikiCollabPublisher}
 * (a **private** Mercure update); the browser never publishes itself.
 */
interface WikiCollabPublisherInterface
{
    /**
     * Publishes the JSON message `{t: 'u'|'a', c: clientId, d: base64}` on the private topic of a page.
     *
     * @param string $topic topic IRI of the page
     * @param string $message JSON message
     */
    public function publish(string $topic, string $message): void;
}
