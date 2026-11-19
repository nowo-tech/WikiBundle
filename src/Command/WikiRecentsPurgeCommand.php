<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_numeric;
use function sprintf;

/**
 * 1.45 (owner decision B): deletes the recent visits older than `recents.retention_days` (90 by default), for people
 * who have not opened a page since (each visit already prunes its person's old rows). Bounded: at most `--batch-size`
 * rows per transaction and `--max-batches` transactions per run; run it from a scheduler, e.g. daily. Does nothing when
 * the visits table does not exist.
 */
#[AsCommand(
    name: 'wiki:recents:purge',
    description: 'Delete the wiki recent visits older than recents.retention_days',
)]
final class WikiRecentsPurgeCommand extends Command
{
    /**
     * @param int $retentionDays `recents.retention_days`
     */
    public function __construct(
        private readonly WikiQuickAccessStore $store,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows deleted per transaction', '500')
            ->addOption('max-batches', null, InputOption::VALUE_REQUIRED, 'Transactions per run at most', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $batch   = $input->getOption('batch-size');
        $batches = $input->getOption('max-batches');
        $batch   = is_numeric($batch) ? (int) $batch : 0;
        $batches = is_numeric($batches) ? (int) $batches : 0;
        if ($batch < 1 || $batch > 10000 || $batches < 1 || $batches > 100000) {
            $io->error('--batch-size must be 1 to 10000 and --max-batches 1 to 100000.');

            return Command::INVALID;
        }

        $before  = (new DateTimeImmutable())->modify('-' . $this->retentionDays . ' days');
        $deleted = 0;
        for ($round = 0; $round < $batches; ++$round) {
            $rows = $this->store->purgeVisitsBefore($before, $batch);
            $deleted += $rows;
            if ($rows < $batch) {
                break;
            }
        }
        $io->success(sprintf('%d visit(s) older than %d day(s) deleted.', $deleted, $this->retentionDays));

        return Command::SUCCESS;
    }
}
