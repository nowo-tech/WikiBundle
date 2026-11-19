<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use JsonException;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * 1.45: exports (`--export`) or erases a person's favourites and recent visits (personal data, right to erasure). The
 * rows keep the user's id without a foreign key, so they outlive the account. Always registered (audit VC-3): it reads
 * and erases every table of the two that exists, whether or not `favourites.enabled` / `recents.enabled` is on now.
 * `wiki:comments:erase-user` erases the same rows as part of a person's erasure.
 */
#[AsCommand(
    name: 'wiki:quick-access:erase-user',
    description: 'Export (--export) or erase the wiki favourites and recently viewed pages of a person',
)]
final class WikiQuickAccessEraseUserCommand extends Command
{
    public function __construct(private readonly WikiQuickAccessStore $store)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'The person\'s id (the user\'s getId())')
            ->addOption('export', null, InputOption::VALUE_NONE, 'Print the rows as JSON, change nothing');
    }

    /**
     * @throws JsonException never (the export holds strings only)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user-id');
        if (!is_string($userId) || !WikiStoredUserRef::isValid($userId)) {
            $io->error(sprintf('The user id must be 1 to %d printable characters.', WikiStoredUserRef::MAX_LENGTH));

            return Command::INVALID;
        }
        if ($input->getOption('export') === true) {
            $output->writeln(json_encode($this->store->exportUser($userId), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d row(s) erased.', $this->store->eraseUser($userId)));

        return Command::SUCCESS;
    }
}
