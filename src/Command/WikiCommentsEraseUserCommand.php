<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use InvalidArgumentException;
use JsonException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Privacy export and erasure of a user's comments (1.16, audit EI-L6). `--export` prints their comments, reactions
 * and the threads they started or resolved as JSON (1.16.1);
 * otherwise they are unlinked from their comments, threads and resolutions, their reactions are deleted and their
 * comment bodies blanked ("Comment deleted"; `--keep-bodies` keeps the text, unattributed), after a confirmation unless
 * `--force` / `--no-interaction` (1.16.1). Mentions of the user in other people's comments stay. Hosts may call
 * {@see WikiCommentService::exportAuthor()} / {@see WikiCommentService::eraseAuthor()} directly instead.
 */
#[AsCommand(
    name: 'wiki:comments:erase-user',
    description: 'Export (--export) or erase a user\'s wiki comments (privacy requests)',
)]
final class WikiCommentsEraseUserCommand extends Command
{
    public function __construct(
        private readonly WikiAuthorResolver $authorResolver,
        private readonly ?WikiCommentService $comments = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'User id (or e-mail when the user class has getEmail())')
            ->addOption('export', null, InputOption::VALUE_NONE, 'Print the user\'s comments as JSON, change nothing')
            ->addOption('keep-bodies', null, InputOption::VALUE_NONE, 'Unlink the user but keep the text of their comments')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Erase without asking for confirmation (also implied by --no-interaction)');
    }

    /**
     * @throws JsonException never (the export holds strings only)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->comments instanceof WikiCommentService) {
            $io->note('Comments are disabled (nowo_wiki.comments.enabled: false): nothing to export or erase.');

            return Command::SUCCESS;
        }
        try {
            $user = $this->authorResolver->resolveByIdentifier((string) $input->getArgument('user'));
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('export') === true) {
            $output->writeln(json_encode($this->comments->exportAuthorData($user), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $keep = $input->getOption('keep-bodies') === true;
        // 1.16.1 (audit EI-I17): an erasure cannot be undone; ask unless --force or --no-interaction.
        if ($input->getOption('force') !== true && $input->isInteractive()
            && !$io->confirm(sprintf('%s the wiki comments of "%s"? This cannot be undone.', $keep ? 'Unlink' : 'Erase', (string) $input->getArgument('user')), false)) {
            $io->note('Nothing was changed.');

            return Command::SUCCESS;
        }
        $io->success(sprintf('%d comment(s) %s; reactions deleted, threads and resolutions unlinked.', $this->comments->eraseAuthor($user, !$keep), $keep ? 'unlinked' : 'erased'));

        return Command::SUCCESS;
    }
}
