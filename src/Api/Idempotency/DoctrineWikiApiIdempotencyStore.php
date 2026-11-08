<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Nowo\WikiBundle\Api\Hook\WikiApiIdempotencyStoreInterface;
use Nowo\WikiBundle\Api\Value\WikiApiIdempotencyClaim;
use Nowo\WikiBundle\Api\Value\WikiApiIdempotencyOutcome;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;

use function is_numeric;
use function is_string;

/**
 * 1.24 (spec 218): the bundle's idempotency store, one row per token and key. The primary key makes the first claim
 * win. A claim left unfinished for {@see self::ABANDONED_SECONDS} (a process that died) is taken over. Expired rows go
 * on each claim, and with `nowo:wiki:api:purge-idempotency`.
 */
final readonly class DoctrineWikiApiIdempotencyStore implements WikiApiIdempotencyStoreInterface
{
    public const ABANDONED_SECONDS = 300;

    public function __construct(
        private WikiEntityManagerProvider $entityManager,
        private string $table = 'wiki_api_idempotency',
    ) {
    }

    public function claim(string $scope, string $keyHash, string $fingerprint, DateTimeImmutable $expiresAt): WikiApiIdempotencyClaim
    {
        $now = new DateTimeImmutable();
        $this->purgeExpired($now);
        $connection = $this->entityManager->get()->getConnection();
        try {
            $connection->insert($this->table, [
                'scope'       => $scope,
                'key_hash'    => $keyHash,
                'fingerprint' => $fingerprint,
                'created_at'  => $now,
                'expires_at'  => $expiresAt,
            ], ['created_at' => Types::DATETIME_IMMUTABLE, 'expires_at' => Types::DATETIME_IMMUTABLE]);

            return new WikiApiIdempotencyClaim(WikiApiIdempotencyOutcome::Claimed);
        } catch (UniqueConstraintViolationException) {
        }

        /** @var array{fingerprint: string, status: mixed, body: mixed, wiki_slug: mixed, page_id: mixed, created_at: string}|false $row */
        $row = $connection->fetchAssociative('SELECT fingerprint, status, body, wiki_slug, page_id, created_at FROM ' . $this->table . ' WHERE scope = ? AND key_hash = ?', [$scope, $keyHash]);
        if ($row !== false && $row['fingerprint'] !== $fingerprint) {
            return new WikiApiIdempotencyClaim(WikiApiIdempotencyOutcome::Mismatch);
        }
        if ($row === false || !is_numeric($row['status'])) {
            // No row: purged in between, so the client's next try decides. A recent claim still runs.
            if ($row === false || $now->getTimestamp() - (new DateTimeImmutable($row['created_at']))->getTimestamp() < self::ABANDONED_SECONDS) {
                return new WikiApiIdempotencyClaim(WikiApiIdempotencyOutcome::InFlight);
            }
            $taken = $connection->executeStatement(
                'UPDATE ' . $this->table . ' SET created_at = ? WHERE scope = ? AND key_hash = ? AND status IS NULL AND created_at = ?',
                [$now, $scope, $keyHash, $row['created_at']],
                [Types::DATETIME_IMMUTABLE, Types::STRING, Types::STRING, Types::STRING],
            );

            return new WikiApiIdempotencyClaim($taken === 1 ? WikiApiIdempotencyOutcome::Claimed : WikiApiIdempotencyOutcome::InFlight);
        }

        return new WikiApiIdempotencyClaim(
            WikiApiIdempotencyOutcome::Completed,
            (int) $row['status'],
            is_string($row['body']) ? $row['body'] : '',
            is_string($row['wiki_slug']) ? $row['wiki_slug'] : null,
            is_string($row['page_id']) ? $row['page_id'] : null,
        );
    }

    public function complete(string $scope, string $keyHash, int $status, string $body, ?string $wikiSlug, ?string $pageId): void
    {
        $this->entityManager->get()->getConnection()->update($this->table, ['status' => $status, 'body' => $body, 'wiki_slug' => $wikiSlug, 'page_id' => $pageId], ['scope' => $scope, 'key_hash' => $keyHash]);
    }

    public function release(string $scope, string $keyHash): void
    {
        $this->entityManager->get()->getConnection()->executeStatement('DELETE FROM ' . $this->table . ' WHERE scope = ? AND key_hash = ? AND status IS NULL', [$scope, $keyHash]);
    }

    public function forget(string $scope): void
    {
        $this->entityManager->get()->getConnection()->delete($this->table, ['scope' => $scope]);
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        return (int) $this->entityManager->get()->getConnection()->executeStatement('DELETE FROM ' . $this->table . ' WHERE expires_at < ?', [$now], [Types::DATETIME_IMMUTABLE]);
    }
}
