<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Purges the deletions older than `trash.retention_days` (1.14, T065): pages, revisions, host-object links and
 * attachments go for good. Run it daily (cron, Scheduler); `--dry-run` only counts.
 */
#[AsCommand(
    name: 'wiki:trash:purge',
    description: 'Delete for good the trashed pages older than the retention (trash.retention_days)',
)]
final class WikiTrashPurgeCommand extends Command
{
    public function __construct(private readonly ?WikiTrashService $trash = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count what would be purged, delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->trash instanceof WikiTrashService) {
            $io->note('The trash is disabled (nowo_wiki.trash.enabled: false): nothing to purge.');

            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run') === true;
        $result = $this->trash->purgeExpired(new DateTimeImmutable(), $dryRun);
        $io->success(sprintf(
            $dryRun ? '%d deletion(s) with %d page(s) are older than %d days (dry run: nothing deleted).' : '%d deletion(s) with %d page(s) older than %d days purged.',
            $result['batches'],
            $result['pages'],
            $this->trash->retentionDays(),
        ));

        return Command::SUCCESS;
    }
}
