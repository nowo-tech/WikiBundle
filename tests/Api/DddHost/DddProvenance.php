<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;

/**
 * The DDD host's write provenance (spec 234): every write of this host comes from its deploy token. Stateless, as a
 * provider in a kept-alive worker must be.
 */
final readonly class DddProvenance implements WikiWriteProvenanceProviderInterface
{
    public function current(): WikiWriteProvenance
    {
        return new WikiWriteProvenance(WikiWriteProvenance::CHANNEL_API, 'Deploy token');
    }
}
