<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218): what an idempotency store answers for a key.
 */
enum WikiApiIdempotencyOutcome
{
    /** The caller goes ahead and then completes or releases the claim. */
    case Claimed;

    /** The first attempt finished: the claim holds its answer. */
    case Completed;

    /** The first attempt is still running. */
    case InFlight;

    /** The key was used for a different request. */
    case Mismatch;
}
