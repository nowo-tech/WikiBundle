<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Value\WikiApiIdempotencyClaim;

/**
 * 1.24 (spec 218 FR-032): where `Idempotency-Key` answers are kept. The bundle's table by default (one store per bundle,
 * Beacon Q-B4); a host replaces it with `api.hooks.idempotency_store`. It grants nothing: a replay is checked again.
 */
interface WikiApiIdempotencyStoreInterface
{
    public function claim(string $scope, string $keyHash, string $fingerprint, DateTimeImmutable $expiresAt): WikiApiIdempotencyClaim;

    public function complete(string $scope, string $keyHash, int $status, string $body, ?string $wikiSlug, ?string $pageId): void;

    public function release(string $scope, string $keyHash): void;

    /** Every key of a token (token revoked, person erased). */
    public function forget(string $scope): void;

    public function purgeExpired(DateTimeImmutable $now): int;
}
