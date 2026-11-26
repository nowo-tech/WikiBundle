<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Symfony\Component\Security\Core\User\UserInterface;

use function count;
use function max;
use function str_replace;

/**
 * 2.0.1 (Beacon 207 T030, DATA-WIKI-COLLAB-SEEDEDBY): the seed claims (`seeded_by`, `seeded_at`) of the default
 * co-editing store's document table (`<prefix>_page_collab_documents`), for a person's privacy export and erasure and
 * for the one-shot clean-up of the raw identifiers 2.0.0 and earlier stored.
 *
 * A person's claims are found by their keyed hash ({@see WikiCollabSeedClaimant::user()}) and, for rows not cleaned up
 * yet, by their raw identifier; every row is compared again byte for byte in PHP and then written by its primary key
 * with the value that was read (a claim taken by someone else meanwhile is left alone). Writes go through DBAL inside the
 * wiki's runner ({@see WikiTransactionRunner::runOnConnection()}): one unit per batch, nothing flushed, the host's entity
 * manager never touched. Lock conflicts surface as the runner's transient failure. It acts on the table when it exists,
 * whether or not co-editing is on now; a host store (`collaboration.store`) keeps its own data and is not touched.
 *
 * Stateless: nothing is kept between calls (worker mode).
 *
 * @internal
 */
final readonly class WikiCollabSeedClaims
{
    /** Rows read per batch (keyset on the page id). */
    public const BATCH = 500;

    /**
     * @param string $secret `kernel.secret` (the key of the claimant hash)
     * @param int $batchSize rows read, and cleared in one unit, per batch of a person's erasure and export
     */
    public function __construct(
        private WikiTransactionRunner $runner,
        private string $secret = '',
        private int $batchSize = self::BATCH,
    ) {
    }

    /**
     * The pages whose co-editing log the person claims to seed, for a privacy export (page id and time; the claim
     * itself is shown as `you`, never as the stored hash).
     *
     * @return list<array{page_id: string, seeded_at: string|null, seeded_by: string}>
     */
    public function exportUser(object $user): array
    {
        $bases = $this->bases($user);
        if ($bases === [] || !$this->tableExists()) {
            return [];
        }
        $export = [];
        foreach ($this->batchesOf($bases) as $rows) {
            foreach ($rows as $row) {
                $export[] = ['page_id' => $row['page_id'], 'seeded_at' => $this->date($row['seeded_at']), 'seeded_by' => 'you'];
            }
        }

        return $export;
    }

    /**
     * Clears the person's seed claims (`seeded_by` and `seeded_at` set to NULL): one unit per keyset batch of at most
     * `$batchSize` rows (audit ZCK-WK-L1: never all of a person's rows in one transaction). The next editor of each
     * page claims the seed again. A batch that fails leaves the earlier ones cleared; run the erasure again.
     *
     * @return int the claims cleared
     */
    public function eraseUser(object $user): int
    {
        $bases = $this->bases($user);
        if ($bases === [] || !$this->tableExists()) {
            return 0;
        }

        $cleared = 0;
        foreach ($this->batchesOf($bases) as $rows) {
            $cleared += $this->clear($rows);
        }

        return $cleared;
    }

    /**
     * The one-shot clean-up of 2.0.1: clears every claim that still holds a raw identifier (or, with `$all`, every
     * claim, e.g. after `kernel.secret` changed). Keyset batches on the page id, one unit per batch.
     *
     * @param bool $all clear the hashed claims too
     * @param bool $dryRun count only
     * @param int $batchSize rows read per batch (1 to 10,000)
     *
     * @throws InvalidArgumentException on a batch size out of range
     *
     * @return array{found: int, cleared: int}
     */
    public function clearLegacy(bool $all = false, bool $dryRun = false, int $batchSize = self::BATCH): array
    {
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new InvalidArgumentException('The batch size must be between 1 and 10000.');
        }
        if (!$this->tableExists()) {
            return ['found' => 0, 'cleared' => 0];
        }
        $found   = 0;
        $cleared = 0;
        $after   = '';
        do {
            $rows  = $this->page($after, $batchSize, null);
            $stale = [];
            foreach ($rows as $row) {
                $after = $row['page_id'];
                if ($all || !WikiCollabSeedClaimant::isHashed($row['seeded_by'])) {
                    $stale[] = $row;
                }
            }
            $found += count($stale);
            if (!$dryRun && $stale !== []) {
                $cleared += $this->clear($stale);
            }
        } while (count($rows) === $batchSize);

        return ['found' => $found, 'cleared' => $cleared];
    }

    /**
     * The claimants of the person: the keyed hash (with a secret) and the raw identifier of rows not cleaned up yet.
     *
     * @return list<string>
     */
    private function bases(object $user): array
    {
        if (!$user instanceof UserInterface || $user->getUserIdentifier() === '') {
            return [];
        }
        $identifier = $user->getUserIdentifier();

        return $this->secret === '' ? [$identifier] : [WikiCollabSeedClaimant::user($identifier, $this->secret), $identifier];
    }

    /**
     * The claimed rows of the person, one keyset batch at a time, compared byte for byte in PHP: exactly the base, or
     * the base followed by `#` and a tab id of 1 to 16 digits ({@see WikiCollabSeedClaimant::belongsTo()}). For a raw
     * 2.0.0 base `bob` that is user `bob` in a tab, or a user whose identifier is literally `bob#42` without a tab: the
     * 2.0.0 format cannot tell them apart (audit ZCK-WK-I1), which is why the clean-up command should run right after
     * the upgrade; hashed claims have no such ambiguity (a hash never contains `#`).
     *
     * @param list<string> $bases
     *
     * @return iterable<list<array{page_id: string, seeded_by: string, seeded_at: mixed}>>
     */
    private function batchesOf(array $bases): iterable
    {
        $after = '';
        $size  = max(1, $this->batchSize);
        do {
            $batch = $this->page($after, $size, $bases);
            $rows  = [];
            foreach ($batch as $row) {
                $after = $row['page_id'];
                foreach ($bases as $base) {
                    if (WikiCollabSeedClaimant::belongsTo($row['seeded_by'], $base)) {
                        $rows[] = $row;

                        break;
                    }
                }
            }
            yield $rows;
        } while (count($batch) === $size);
    }

    /**
     * One page of claimed rows after `$after` (by page id), narrowed in SQL to the claimants when given.
     *
     * @param list<string>|null $bases
     *
     * @return list<array{page_id: string, seeded_by: string, seeded_at: mixed}>
     */
    private function page(string $after, int $limit, ?array $bases): array
    {
        $query = $this->connection()->createQueryBuilder()
            ->select('page_id', 'seeded_by', 'seeded_at')
            ->from($this->table())
            ->where('seeded_by IS NOT NULL')
            ->andWhere('page_id > :after')
            ->setParameter('after', $after)
            ->orderBy('page_id', 'ASC')
            ->setMaxResults($limit);
        if ($bases !== null) {
            $or = [];
            foreach ($bases as $index => $base) {
                $or[] = 'seeded_by = :b' . $index;
                $or[] = 'seeded_by LIKE :p' . $index . " ESCAPE '!'";
                $query->setParameter('b' . $index, $base)->setParameter('p' . $index, str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $base) . '#%');
            }
            $query->andWhere($query->expr()->or(...$or));
        }

        $rows = [];
        foreach ($query->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = ['page_id' => (string) $row['page_id'], 'seeded_by' => (string) $row['seeded_by'], 'seeded_at' => $row['seeded_at']];
        }

        return $rows;
    }

    /**
     * Clears the given claims by primary key, each only while it still holds, byte for byte, the value that was read
     * (audit ZCK-WK-I2: on MySQL / MariaDB both sides are compared as binary strings, never under the column's case- or
     * PAD-insensitive collation); one unit.
     *
     * @param list<array{page_id: string, seeded_by: string, seeded_at: mixed}> $rows
     */
    private function clear(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $table = $this->table();
        $exact = $this->connection()->getDatabasePlatform() instanceof AbstractMySQLPlatform ? 'CAST(seeded_by AS BINARY) = CAST(? AS BINARY)' : 'seeded_by = ?';

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($rows, $table, $exact): int {
            $connection = $this->connection();
            $cleared    = 0;
            foreach ($rows as $row) {
                $cleared += (int) $connection->executeStatement(
                    'UPDATE ' . $table . ' SET seeded_by = NULL, seeded_at = NULL WHERE page_id = ? AND ' . $exact,
                    [$row['page_id'], $row['seeded_by']],
                );
            }

            return $cleared;
        });
    }

    private function date(mixed $value): ?string
    {
        $date = $this->connection()->convertToPHPValue($value, Types::DATETIME_IMMUTABLE);

        return $date instanceof DateTimeImmutable ? $date->format(DateTimeInterface::ATOM) : null;
    }

    private function tableExists(): bool
    {
        return $this->connection()->createSchemaManager()->tablesExist([$this->table()]);
    }

    private function table(): string
    {
        return $this->runner->manager()->getClassMetadata(WikiCollabDocument::class)->getTableName();
    }

    private function connection(): Connection
    {
        return $this->runner->manager()->getConnection();
    }
}
