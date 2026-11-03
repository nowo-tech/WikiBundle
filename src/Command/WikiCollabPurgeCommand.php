<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
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
 * Deletes the co-editing logs of pages nobody touched for a while (1.13, audit CW-I9). A log only matters while a
 * session is live — the saved Markdown is the source of truth — so purging an idle one loses nothing; the next editor
 * seeds a new log. Run it daily (cron / scheduler).
 */
#[AsCommand(
    name: 'wiki:collab:purge',
    description: 'Delete the co-editing logs of pages untouched for N days',
)]
final class WikiCollabPurgeCommand extends Command
{
    /**
     * @param WikiCollabStateStoreInterface|null $store the co-editing log store (null: collaboration disabled)
     * @param WikiCollabService|null $collab purges and tells live editors to reload (1.13.1, CW-I10)
     */
    public function __construct(
        private readonly ?WikiCollabStateStoreInterface $store = null,
        private readonly ?WikiCollabService $collab = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Days without activity (at least 1)', '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = $input->getOption('older-than');
        if (!$this->store instanceof WikiCollabStateStoreInterface) {
            $io->note('Co-editing is disabled (nowo_wiki.collaboration.enabled: false): nothing to purge.');

            return Command::SUCCESS;
        }

        // 1.13.1 (CW-I10): at least one day, so a log that is in use right now is never purged.
        if (!is_string($days) || !ctype_digit($days) || (int) $days < 1) {
            $io->error('--older-than must be a whole number of days, at least 1.');

            return Command::FAILURE;
        }

        $before = new DateTimeImmutable(sprintf('-%d days', (int) $days));
        $purged = $this->collab instanceof WikiCollabService ? $this->collab->purge($before) : $this->store->purge($before);
        $io->success(sprintf('%d co-editing log(s) purged.', $purged));

        return Command::SUCCESS;
    }
}
