<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function implode;
use function is_numeric;
use function is_string;
use function sprintf;
use function usleep;

/**
 * 1.45 (`backlinks.enabled`): fills the backlink index from the current revision of every page — once after enabling
 * backlinks (pages saved before have no rows), and whenever a save's index write was skipped by a lock conflict. Each
 * page is indexed in its own short unit under its row lock ({@see WikiPageReferenceIndex::reindex()}), so it runs
 * beside live saves; pages are read in id order (keyset batches, bounded memory). Nothing is flushed or cleared. A page
 * that meets a lock conflict is tried {@see self::ATTEMPTS} times, then logged and skipped; the command then exits with
 * FAILURE and lists the skipped ids. `--after=<id>` resumes after a page id.
 */
#[AsCommand(
    name: 'wiki:backlinks:rebuild',
    description: 'Fill the wiki backlink index from the current revision of every page',
)]
final class WikiBacklinksRebuildCommand extends Command
{
    /** Default number of page ids read per batch. */
    public const BATCH = 200;

    /** Attempts per page (the first included) when it meets a lock conflict. */
    public const ATTEMPTS = 3;

    /**
     * @param int $backoffMilliseconds wait before a retry (0 in tests)
     */
    public function __construct(
        private readonly WikiPageReferenceIndex $index,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $backoffMilliseconds = 200,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Page ids read per batch', (string) self::BATCH)
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Resume after this page id (the last id a previous run printed)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $size  = $input->getOption('batch-size');
        $batch = is_numeric($size) ? (int) $size : 0;
        if ($batch < 1 || $batch > 10000) {
            $io->error('--batch-size must be an integer from 1 to 10000.');

            return Command::INVALID;
        }

        $after   = $input->getOption('after');
        $after   = is_string($after) && $after !== '' ? $after : null;
        $indexed = 0;
        $skipped = [];
        do {
            $ids = $this->index->pageIds($after, $batch);
            foreach ($ids as $id) {
                $result = $this->reindex($id);
                if ($result === null) {
                    $skipped[] = $id;
                } elseif ($result) {
                    ++$indexed;
                }
                $after = $id;
            }
        } while (count($ids) === $batch);

        if ($skipped !== []) {
            $io->error(sprintf('%d page(s) indexed; %d skipped after lock conflicts: %s. Run the command again (or with --after=<id>) when the load is lower.', $indexed, count($skipped), implode(', ', $skipped)));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d page(s) indexed%s.', $indexed, $after !== null ? sprintf(' (last page id %s)', $after) : ''));

        return Command::SUCCESS;
    }

    /**
     * One page, with a few retries on a lock conflict (audit VC-4).
     *
     * @return bool|null whether the page still exists; null when every attempt met a lock conflict (skipped)
     */
    private function reindex(string $id): ?bool
    {
        for ($attempt = 1;; ++$attempt) {
            try {
                return $this->index->reindex($id);
            } catch (TransientStorageFailure|RetryableException $failure) {
                if ($attempt >= self::ATTEMPTS) {
                    $this->logger?->warning('Wiki: wiki:backlinks:rebuild skipped a page after lock conflicts.', ['bundle' => 'nowo_wiki', 'page' => $id, 'exception_class' => $failure::class]);

                    return null;
                }
                usleep($this->backoffMilliseconds * 1000 * $attempt);
            }
        }
    }
}
