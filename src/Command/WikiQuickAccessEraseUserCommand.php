<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function mb_strlen;
use function sprintf;

/**
 * 1.45: erases a person's favourites and recent visits (right to erasure). The rows keep the user's id without a
 * foreign key, so they outlive the account: call it when a person is deleted (or from the host's own erasure flow
 * through {@see WikiQuickAccessStore::eraseUser()}).
 */
#[AsCommand(
    name: 'wiki:quick-access:erase-user',
    description: 'Erase the wiki favourites and recently viewed pages of a person',
)]
final class WikiQuickAccessEraseUserCommand extends Command
{
    /**
     * @param bool $favourites `favourites.enabled`
     * @param bool $recents `recents.enabled`
     */
    public function __construct(
        private readonly WikiQuickAccessStore $store,
        private readonly bool $favourites,
        private readonly bool $recents,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user-id', InputArgument::REQUIRED, 'The person\'s id (the user\'s getId())');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user-id');
        if (!is_string($userId) || $userId === '' || mb_strlen($userId) > WikiStoredUserRef::MAX_LENGTH) {
            $io->error(sprintf('The user id must be 1 to %d characters.', WikiStoredUserRef::MAX_LENGTH));

            return Command::INVALID;
        }

        $io->success(sprintf('%d row(s) erased.', $this->store->eraseUser($userId, $this->favourites, $this->recents)));

        return Command::SUCCESS;
    }
}
