<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Thins the revisions older than `history.retention_days` to one per session and day (1.15, D-W6); named versions
 * and the current revision are kept. Run it daily from a single scheduler (cron, Scheduler); `--dry-run` only counts.
 */
#[AsCommand(
    name: 'wiki:history:thin',
    description: 'Thin old revisions to one per session and day (history.retention_days); named versions are kept',
)]
final class WikiHistoryThinCommand extends Command
{
    public function __construct(private readonly ?WikiHistoryThinner $thinner = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count what would be removed, delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->thinner instanceof WikiHistoryThinner) {
            $io->note('The history timeline is disabled (nowo_wiki.history.timeline: false): nothing to thin.');

            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run') === true;
        $result = $this->thinner->thin(new DateTimeImmutable(), $dryRun);
        $io->success(sprintf(
            $dryRun ? '%d revision(s) of %d page(s) older than %d days would be thinned (dry run: nothing deleted).' : '%d revision(s) of %d page(s) older than %d days thinned.',
            $result['revisions'],
            $result['pages'],
            $this->thinner->retentionDays(),
        ));

        return Command::SUCCESS;
    }
}
