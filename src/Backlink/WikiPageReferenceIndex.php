<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Backlink;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Command\WikiBacklinksRebuildCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\Uuid;

use function array_diff;
use function array_flip;
use function array_keys;
use function array_slice;
use function array_values;
use function is_string;

/**
 * 1.45 (`backlinks.enabled`): the backlink index — one {@see WikiPageReference} row per page a page's **current**
 * revision links (`[[wiki:<uuid>]]` and `[[wiki:<uuid>#anchor]]`, found by {@see WikiMarkdownReferenceExtractor}, the
 * renderer's own parser with the Milkdown editor: exactly the links a reader sees, never inside code).
 *
 * {@see self::reindex()} runs in its own unit of the wiki's runner, on the connection (nothing is flushed; an open host
 * entity manager is never flushed, cleared or reset): it locks the page row, reads the page's current revision under
 * the lock and writes the difference. Two saves of one page that index at once are therefore serialised, and whichever
 * indexes last indexes the latest revision (the index never keeps an older revision's links). Lock conflicts surface as
 * the runner's {@see TransientStorageFailure}. Stateless (worker mode).
 */
final readonly class WikiPageReferenceIndex
{
    /** References kept per page at most (the Milkdown renderer already caps them at `max_references`). */
    public const MAX_REFERENCES = 1000;

    public function __construct(
        private WikiTransactionRunner $runner,
        private WikiMarkdownReferenceExtractor $references,
    ) {
    }

    /**
     * Writes the references of the page's current revision; a page that is gone is skipped.
     *
     * @return bool whether the page still exists
     */
    public function reindex(string $pageId): bool
    {
        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($pageId): bool {
            if (!$this->runner->lockRow(WikiPage::class, $pageId)) {
                return false;
            }

            $connection = $this->manager()->getConnection();
            $table      = $this->table();
            $wanted     = $this->targets($pageId);
            /** @var list<string> $stored */
            $stored = $connection->createQueryBuilder()
                ->select('target_page_id')
                ->from($table)
                ->where('source_page_id = :page')
                ->setParameter('page', $pageId)
                ->executeQuery()
                ->fetchFirstColumn();

            $removed = array_values(array_diff($stored, $wanted));
            if ($removed !== []) {
                $connection->executeStatement(
                    'DELETE FROM ' . $table . ' WHERE source_page_id = ? AND target_page_id IN (?)',
                    [$pageId, $removed],
                    [ParameterType::STRING, ArrayParameterType::STRING],
                );
            }
            foreach (array_diff($wanted, $stored) as $target) {
                $connection->insert($table, ['id' => Uuid::generate()->toString(), 'source_page_id' => $pageId, 'target_page_id' => $target]);
            }

            return true;
        });
    }

    /** Whether a transaction is open on the wiki's connection (a caller's unit). */
    public function inTransaction(): bool
    {
        return $this->manager()->getConnection()->isTransactionActive();
    }

    /**
     * The ids of the pages of a batch for {@see WikiBacklinksRebuildCommand}, in id order,
     * after `$after`.
     *
     * @return list<string>
     */
    public function pageIds(?string $after, int $batch): array
    {
        $query = $this->manager()->createQueryBuilder()
            ->select('p.id')
            ->from(WikiPage::class, 'p')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($batch);
        if ($after !== null) {
            $query->where('p.id > :after')->setParameter('after', $after);
        }

        /** @var list<string> $ids */
        $ids = $query->getQuery()->getSingleColumnResult();

        return $ids;
    }

    /**
     * The distinct pages the current revision links, without the page itself.
     *
     * @return list<string>
     */
    private function targets(string $pageId): array
    {
        /** @var list<mixed> $markdown */
        $markdown = $this->manager()->createQueryBuilder()
            ->select('r.contentMarkdown')
            ->from(WikiPage::class, 'p')
            ->join(WikiPageRevision::class, 'r', 'WITH', 'r = p.currentRevision')
            ->where('p.id = :page')
            ->setParameter('page', $pageId)
            ->getQuery()
            ->getSingleColumnResult();
        $source = $markdown[0] ?? null;

        $ids = array_flip($this->references->pageIds(is_string($source) ? $source : null));
        unset($ids[$pageId]);

        return array_slice(array_keys($ids), 0, self::MAX_REFERENCES);
    }

    private function table(): string
    {
        return $this->manager()->getClassMetadata(WikiPageReference::class)->getTableName();
    }

    private function manager(): EntityManagerInterface
    {
        return $this->runner->manager();
    }
}
