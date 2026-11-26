<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaims;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function ctype_digit;
use function is_string;
use function sprintf;

/**
 * 2.0.1 (Beacon 207 T030, DATA-WIKI-COLLAB-SEEDEDBY): the one-shot clean-up after the upgrade. Up to 2.0.0 the
 * co-editing seed claim (`<prefix>_page_collab_documents.seeded_by`) stored the claimant's user identifier (an e-mail in
 * many hosts); 2.0.1 stores a keyed hash. This command clears (sets to NULL, with `seeded_at`) every claim that still
 * holds a raw identifier. A claim only matters for `seed_timeout` seconds and the new code never matches an old one, so
 * nothing is lost: the next editor claims the seed again. `--all` clears the hashed claims too (after `kernel.secret`
 * changed). Safe to run again; keyset batches, one transaction per batch.
 */
#[AsCommand(
    name: 'wiki:collab:seed-claims:clear',
    description: 'Clear the co-editing seed claims that still hold a raw user identifier (2.0.1 upgrade)',
)]
final class WikiCollabSeedClaimsClearCommand extends Command
{
    public function __construct(private readonly WikiCollabSeedClaims $claims)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Clear every seed claim, hashed ones too (e.g. after kernel.secret changed)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the claims to clear, change nothing')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows read per batch (1 to 10000)', (string) WikiCollabSeedClaims::BATCH);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $batch = $input->getOption('batch-size');
        if (!is_string($batch) || !ctype_digit($batch) || (int) $batch < 1 || (int) $batch > 10000) {
            $io->error('--batch-size must be a whole number between 1 and 10000.');

            return Command::FAILURE;
        }
        $dryRun = $input->getOption('dry-run') === true;
        $result = $this->claims->clearLegacy($input->getOption('all') === true, $dryRun, (int) $batch);
        if ($dryRun) {
            $io->note(sprintf('%d seed claim(s) to clear. Nothing was changed.', $result['found']));

            return Command::SUCCESS;
        }
        $io->success(sprintf('%d seed claim(s) cleared.', $result['cleared']));

        return Command::SUCCESS;
    }
}
