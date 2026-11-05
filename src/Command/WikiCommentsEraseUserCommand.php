<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use InvalidArgumentException;
use JsonException;
use LogicException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Privacy export and erasure of a user's comments (1.16, audit EI-L6). `--export` prints their comments, reactions
 * and the threads they started or resolved as JSON (1.16.1);
 * otherwise they are unlinked from their comments, threads and resolutions, their reactions are deleted and their
 * comment bodies blanked ("Comment deleted"; `--keep-bodies` keeps the text, unattributed), after a confirmation unless
 * `--force` / `--no-interaction` (1.16.1). Since 1.17 (audit FG-L2) the user's `@handle` mentions in other people's
 * comments and thread quotes are erased too ({@see WikiCommentService::eraseMentions()}): the handles of the repeatable
 * `--handle` option plus the user's own `getHandle()` when the user class has one; without any handle a warning says
 * that mentions were kept. Hosts may call {@see WikiCommentService::exportAuthor()} /
 * {@see WikiCommentService::eraseAuthor()} / {@see WikiCommentService::eraseMentions()} directly instead.
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
            ->addOption('handle', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A mention handle of the user (repeatable; the user\'s getHandle() is added when it exists): its @mentions in other people\'s comments are erased')
            ->addOption('mention-label', null, InputOption::VALUE_REQUIRED, 'What each erased mention becomes', WikiCommentService::ERASED_MENTION)
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

        $keep    = $input->getOption('keep-bodies') === true;
        $handles = $this->handles($input, $user);
        $label   = (string) $input->getOption('mention-label');
        foreach ($handles as $handle) {
            if (preg_match(WikiCommentService::HANDLE_PATTERN, $handle) !== 1) {
                $io->error(sprintf('"%s" is not a mention handle. Nothing was changed.', $handle));

                return Command::FAILURE;
            }
        }
        // 1.16.1 (audit EI-I17): an erasure cannot be undone; ask unless --force or --no-interaction.
        if ($input->getOption('force') !== true && $input->isInteractive()
            // 1.16.1 (audit EI2-I4): the resolved account and what will change, so a wrong match shows before it is too late.
            && !$io->confirm(sprintf('%s the %d wiki comment(s) of "%s"%s? This cannot be undone.', $keep ? 'Unlink' : 'Erase', count($this->comments->exportAuthor($user)), $user instanceof UserInterface ? $user->getUserIdentifier() : (string) $input->getArgument('user'), $handles === [] ? '' : ' and their mentions (@' . implode(', @', $handles) . ')'), false)) {
            $io->note('Nothing was changed.');

            return Command::SUCCESS;
        }
        // 1.17 (audit FG-L2): mentions first, so a store or label that cannot erase them stops before anything changed.
        $mentions = 0;
        if ($handles !== []) {
            try {
                $mentions = $this->comments->eraseMentions($handles, $label);
            } catch (InvalidArgumentException|LogicException $exception) {
                $io->error($exception->getMessage() . ' Nothing was changed.');

                return Command::FAILURE;
            }
        }
        $io->success(sprintf('%d comment(s) %s; reactions deleted, threads and resolutions unlinked.', $this->comments->eraseAuthor($user, !$keep), $keep ? 'unlinked' : 'erased'));
        if ($handles === []) {
            $io->warning('Mentions of the user in other people\'s comments were kept: pass --handle=<handle> (or give the user class a getHandle()) to erase them.');
        } else {
            $io->success(sprintf('%d comment(s) or thread(s) with mentions of @%s rewritten.', $mentions, implode(', @', $handles)));
        }

        return Command::SUCCESS;
    }

    /**
     * The `--handle` values and the user's own `getHandle()`, without `@`, distinct.
     *
     * @return list<string>
     */
    private function handles(InputInterface $input, object $user): array
    {
        $handles = [];
        $option  = $input->getOption('handle');
        foreach (is_array($option) ? $option : [] as $handle) {
            $handles[] = (string) $handle;
        }
        if (method_exists($user, 'getHandle')) {
            $own = $user->getHandle();
            if (is_string($own)) {
                $handles[] = $own;
            }
        }
        $distinct = [];
        foreach ($handles as $handle) {
            $handle = trim($handle);
            $handle = str_starts_with($handle, '@') ? substr($handle, 1) : $handle;
            if ($handle !== '') {
                $distinct[strtolower($handle)] = $handle;
            }
        }

        return array_values($distinct);
    }
}
