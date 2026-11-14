<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

/**
 * Spec 234: the default provenance provider — nothing recorded (the revision columns stay NULL).
 */
final readonly class NullWikiWriteProvenanceProvider implements WikiWriteProvenanceProviderInterface
{
    public function current(): ?WikiWriteProvenance
    {
        return null;
    }
}
