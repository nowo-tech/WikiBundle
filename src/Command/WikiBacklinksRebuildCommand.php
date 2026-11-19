<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_numeric;
use function sprintf;

/**
 * 1.45 (`backlinks.enabled`): fills the backlink index from the current revision of every page — once after enabling
 * backlinks (pages saved before have no rows), and whenever a save's index write was skipped by a lock conflict. Each
 * page is indexed in its own short unit under its row lock ({@see WikiPageReferenceIndex::reindex()}), so it runs
 * beside live saves; pages are read in id order (keyset batches, bounded memory). Nothing is flushed or cleared.
 */
#[AsCommand(
    name: 'wiki:backlinks:rebuild',
    description: 'Fill the wiki backlink index from the current revision of every page',
)]
final class WikiBacklinksRebuildCommand extends Command
{
    /** Default number of page ids read per batch. */
    public const BATCH = 200;

    public function __construct(private readonly WikiPageReferenceIndex $index)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Page ids read per batch', (string) self::BATCH);
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

        $indexed = 0;
        $after   = null;
        do {
            $ids = $this->index->pageIds($after, $batch);
            foreach ($ids as $id) {
                $indexed += $this->index->reindex($id) ? 1 : 0;
                $after = $id;
            }
        } while (count($ids) === $batch);

        $io->success(sprintf('%d page(s) indexed.', $indexed));

        return Command::SUCCESS;
    }
}
