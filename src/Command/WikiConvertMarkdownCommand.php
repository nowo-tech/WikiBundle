<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Service\WikiPageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_merge;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Converts the HTML pages of one or more spaces to Markdown (1.11, before or after switching
 * `nowo_wiki.editor.type` to `markdown`): each page whose current revision is HTML gets a **new** Markdown revision
 * converted with {@see WikiMarkdownConverter::htmlToMarkdown()}; older revisions are never changed. Pages already in
 * Markdown and pages without a revision are skipped, so running it again changes nothing. Archived pages are converted
 * too.
 */
#[AsCommand(
    name: 'wiki:convert-markdown',
    description: 'Convert the HTML pages of wiki spaces to Markdown (one new revision per page, history untouched)',
)]
final class WikiConvertMarkdownCommand extends Command
{
    /** Upper bound of archived pages read per space. */
    private const MAX_ARCHIVED_PAGES = 100000;

    /**
     * Wires the repositories, the converter, the page service and the author resolver.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository finds the spaces by slug
     * @param WikiPageRepositoryInterface $pageRepository lists the active and archived pages of a space
     * @param WikiMarkdownConverter $markdownConverter converts the HTML of the current revision
     * @param WikiPageService $pageService appends the Markdown revision
     * @param WikiAuthorResolver $authorResolver resolves --author-id to the user recorded as author
     */
    public function __construct(
        private readonly WikiSpaceRepositoryInterface $spaceRepository,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiMarkdownConverter $markdownConverter,
        private readonly WikiPageService $pageService,
        private readonly WikiAuthorResolver $authorResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('spaces', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Wiki space slug(s)')
            ->addOption('author-id', null, InputOption::VALUE_REQUIRED, 'User id or email recorded as author of the new revisions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be converted without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $authorId = $input->getOption('author-id');
        $dryRun   = (bool) $input->getOption('dry-run');

        if (!is_string($authorId) || $authorId === '') {
            $io->error('The --author-id option is required.');

            return Command::FAILURE;
        }

        $spaces = [];
        $slugs  = $input->getArgument('spaces');
        foreach (is_array($slugs) ? $slugs : [] as $slug) {
            $space = $this->spaceRepository->findFirstBySlug((string) $slug);
            if (!$space instanceof WikiSpace) {
                $io->error(sprintf('Space "%s" not found.', (string) $slug));

                return Command::FAILURE;
            }

            $spaces[] = $space;
        }

        $author = $this->authorResolver->resolveByIdentifier($authorId);
        $totals = ['converted' => 0, 'markdown' => 0, 'empty' => 0];
        foreach ($spaces as $space) {
            foreach ($this->pages($space) as $page) {
                $outcome = $this->convert($page, $author, $dryRun);
                ++$totals[$outcome];
                if ($outcome === 'converted' && $io->isVerbose()) {
                    $io->writeln(sprintf(' - %s/%s', $space->getSlug(), $page->getSlug()));
                }
            }
        }

        $io->success(sprintf(
            '%s: %d converted, %d already in Markdown, %d without revision.',
            $dryRun ? 'Dry run' : 'Conversion finished',
            $totals['converted'],
            $totals['markdown'],
            $totals['empty'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<WikiPage>
     */
    private function pages(WikiSpace $space): array
    {
        return array_merge(
            $this->pageRepository->findActiveBySpace($space),
            $this->pageRepository->findArchivedBySpace($space, self::MAX_ARCHIVED_PAGES),
        );
    }

    /**
     * @return 'converted'|'empty'|'markdown'
     */
    private function convert(WikiPage $page, object $author, bool $dryRun): string
    {
        $revision = $page->getCurrentRevision();
        if (!$revision instanceof WikiPageRevision) {
            return 'empty';
        }

        if ($revision->isMarkdown()) {
            return 'markdown';
        }

        if (!$dryRun) {
            $this->pageService->convertToMarkdown($page, $this->markdownConverter->htmlToMarkdown($revision->getContentHtml()), $author);
        }

        return 'converted';
    }
}
