<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

/**
 * Spec 234: the provenance of the write being made, asked by WikiBundle once per page write (create, save,
 * restore, conversion to Markdown: every new revision). The host decides the channel from the request path (its web
 * UI, its app, its API firewall, its MCP endpoint) and the actor label from the authenticated token; never from a
 * header, a field or a parameter the client sends. Return null when there is nothing to record (a console command):
 * the revision columns stay NULL.
 *
 * The default ({@see NullWikiWriteProvenanceProvider}) returns null. Alias this interface to the host's service. It is
 * called at write time, so it must resolve per call (no value kept across requests in worker mode).
 */
interface WikiWriteProvenanceProviderInterface
{
    public function current(): ?WikiWriteProvenance;
}
