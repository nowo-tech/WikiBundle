<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Interchange\Conversion\WikiConversionFidelity;
use Nowo\WikiBundle\Interchange\Conversion\WikiConversionFidelityAnalyzer;
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
use function array_values;
use function count;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function round;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Converts the HTML pages of one or more spaces to Markdown (1.11, before or after switching
 * `nowo_wiki.editor.type` to `markdown` or `milkdown`): each page whose current revision is HTML gets a **new**
 * Markdown revision converted with {@see WikiMarkdownConverter::htmlToMarkdown()}; older revisions are never changed.
 * Pages already in Markdown and pages without a revision are skipped, so running it again changes nothing. Archived
 * pages are converted too.
 *
 * 1.13: `--page` limits the run to some pages of the spaces; every converted page gets a fidelity entry (what was
 * lost or approximated, {@see WikiConversionFidelityAnalyzer}) printed as a summary and written as JSON with
 * `--report`; `--skip-lossy` leaves pages with losses in HTML and `--fail-on-loss` exits with 1 when any page loses
 * something. With `editor.type: milkdown` the Markdown is written in the Milkdown dialect.
 */
#[AsCommand(
    name: 'wiki:convert-markdown',
    description: 'Convert the HTML pages of wiki spaces to Markdown (one new revision per page, history untouched)',
)]
final class WikiConvertMarkdownCommand extends Command
{
    /** Upper bound of archived pages read per space. */
    private const MAX_ARCHIVED_PAGES = 100000;

    /** `format` of the JSON report. */
    public const REPORT_FORMAT = 'nowo-wiki-conversion-report';

    /** `version` of the JSON report. */
    public const REPORT_VERSION = 1;

    private readonly WikiConversionFidelityAnalyzer $analyzer;

    /**
     * Wires the repositories, the converter, the page service and the author resolver.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository finds the spaces by slug
     * @param WikiPageRepositoryInterface $pageRepository lists the active and archived pages of a space
     * @param WikiMarkdownConverter $markdownConverter converts the HTML of the current revision
     * @param WikiPageService $pageService appends the Markdown revision
     * @param WikiAuthorResolver $authorResolver resolves --author-id to the user recorded as author
     * @param WikiConversionFidelityAnalyzer|null $analyzer measures what each conversion lost (1.13)
     */
    public function __construct(
        private readonly WikiSpaceRepositoryInterface $spaceRepository,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiMarkdownConverter $markdownConverter,
        private readonly WikiPageService $pageService,
        private readonly WikiAuthorResolver $authorResolver,
        ?WikiConversionFidelityAnalyzer $analyzer = null,
    ) {
        parent::__construct();
        $this->analyzer = $analyzer ?? new WikiConversionFidelityAnalyzer();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('spaces', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Wiki space slug(s)')
            ->addOption('author-id', null, InputOption::VALUE_REQUIRED, 'User id or email recorded as author of the new revisions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be converted without writing to the database')
            ->addOption('page', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only the page(s) with this slug in the given spaces (repeatable)')
            ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Write the fidelity report (JSON) to this file')
            ->addOption('skip-lossy', null, InputOption::VALUE_NONE, 'Leave pages whose conversion loses something (constructs or words) in HTML')
            ->addOption('fail-on-loss', null, InputOption::VALUE_NONE, 'Exit with 1 when the conversion of any page loses something');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $authorId  = $input->getOption('author-id');
        $dryRun    = (bool) $input->getOption('dry-run');
        $skipLossy = (bool) $input->getOption('skip-lossy');
        $report    = $input->getOption('report');
        /** @var list<string> $pageSlugs */
        $pageSlugs = $input->getOption('page');

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

        $selected = [];
        foreach ($spaces as $space) {
            $selected[] = [$space, $this->pages($space, $pageSlugs)];
        }

        $missing = $this->missingPages($pageSlugs, $selected);
        if ($missing !== []) {
            $io->error(sprintf('Page(s) not found in the given spaces: %s.', implode(', ', $missing)));

            return Command::FAILURE;
        }

        $author  = $this->authorResolver->resolveByIdentifier($authorId);
        $totals  = ['converted' => 0, 'markdown' => 0, 'empty' => 0, 'lossy' => 0];
        $entries = [];
        foreach ($selected as [$space, $pages]) {
            foreach ($pages as $page) {
                $entry = $this->convert($page, $author, $dryRun, $skipLossy);
                ++$totals[$entry['outcome']];
                $entries[] = $entry;
                if ($entry['outcome'] === 'converted' && $io->isVerbose()) {
                    $io->writeln(sprintf(' - %s/%s', $space->getSlug(), $page->getSlug()));
                }
            }
        }

        $io->success(sprintf(
            '%s: %d converted, %d already in Markdown, %d without revision.%s',
            $dryRun ? 'Dry run' : 'Conversion finished',
            $totals['converted'],
            $totals['markdown'],
            $totals['empty'],
            $totals['lossy'] > 0 ? sprintf(' %d left in HTML (--skip-lossy).', $totals['lossy']) : '',
        ));
        $this->summary($io, $entries);

        if (is_string($report) && $report !== '' && !$this->writeReport($io, $report, $entries, $dryRun)) {
            return Command::FAILURE;
        }

        return (bool) $input->getOption('fail-on-loss') && $this->countLossy($entries) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<string> $pageSlugs
     *
     * @return list<WikiPage>
     */
    private function pages(WikiSpace $space, array $pageSlugs): array
    {
        if ($pageSlugs === []) {
            return array_merge(
                $this->pageRepository->findActiveBySpace($space),
                $this->pageRepository->findArchivedBySpace($space, self::MAX_ARCHIVED_PAGES),
            );
        }

        $pages = [];
        foreach ($pageSlugs as $slug) {
            $page = $this->pageRepository->findBySlug($space, $slug);
            if ($page instanceof WikiPage) {
                $pages[$page->getId()] = $page;
            }
        }

        return array_values($pages);
    }

    /**
     * @param list<string> $pageSlugs
     * @param list<array{0: WikiSpace, 1: list<WikiPage>}> $selected
     *
     * @return list<string>
     */
    private function missingPages(array $pageSlugs, array $selected): array
    {
        $found = [];
        foreach ($selected as [, $pages]) {
            foreach ($pages as $page) {
                $found[$page->getSlug()] = true;
            }
        }

        $missing = [];
        foreach ($pageSlugs as $slug) {
            if (!isset($found[$slug]) && !in_array($slug, $missing, true)) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }

    /**
     * @return array{space: string, page: string, page_id: string, title: string, outcome: 'converted'|'empty'|'lossy'|'markdown', from_revision: int|null, to_revision: int|null, fidelity: WikiConversionFidelity|null}
     */
    private function convert(WikiPage $page, object $author, bool $dryRun, bool $skipLossy): array
    {
        $revision = $page->getCurrentRevision();
        $entry    = [
            'space'         => $page->getSpace()->getSlug(),
            'page'          => $page->getSlug(),
            'page_id'       => $page->getId(),
            'title'         => $page->getTitle(),
            'outcome'       => 'empty',
            'from_revision' => $revision?->getRevisionNumber(),
            'to_revision'   => null,
            'fidelity'      => null,
        ];
        if (!$revision instanceof WikiPageRevision) {
            return $entry;
        }

        if ($revision->isMarkdown()) {
            return ['outcome' => 'markdown'] + $entry;
        }

        $markdown          = $this->markdownConverter->htmlToMarkdown($revision->getContentHtml());
        $entry['fidelity'] = $this->analyzer->analyze($revision->getContentHtml(), $markdown, $this->markdownConverter->dialect);
        if ($skipLossy && !$entry['fidelity']->isLossless()) {
            return ['outcome' => 'lossy'] + $entry;
        }

        if (!$dryRun) {
            $entry['to_revision'] = $this->pageService->convertToMarkdown($page, $markdown, $author)->getRevisionNumber();
        }

        return ['outcome' => 'converted'] + $entry;
    }

    /**
     * Console summary: one row per converted (or left-out) page whose conversion lost or approximated something.
     *
     * @param list<array{space: string, page: string, page_id: string, title: string, outcome: string, from_revision: int|null, to_revision: int|null, fidelity: WikiConversionFidelity|null}> $entries
     */
    private function summary(SymfonyStyle $io, array $entries): void
    {
        $rows       = [];
        $analysed   = 0;
        $lossless   = 0;
        $approxOnly = 0;
        foreach ($entries as $entry) {
            $fidelity = $entry['fidelity'];
            if (!$fidelity instanceof WikiConversionFidelity) {
                continue;
            }

            ++$analysed;
            if ($fidelity->isLossless() && $fidelity->approximated === []) {
                ++$lossless;

                continue;
            }

            if ($fidelity->isLossless()) {
                ++$approxOnly;
            }

            $rows[] = [
                $entry['space'] . '/' . $entry['page'],
                $this->codes($fidelity->lost),
                $this->codes($fidelity->approximated),
                $fidelity->textPreserved() ? 'same' : sprintf('%d%% (-%d / +%d words)', (int) round($fidelity->similarity * 100), $fidelity->wordsMissing, $fidelity->wordsAdded),
            ];
        }

        if ($analysed === 0) {
            return;
        }

        $io->writeln(sprintf('Fidelity: %d lossless, %d approximated, %d with losses.', $lossless, $approxOnly, $analysed - $lossless - $approxOnly));
        if ($rows !== []) {
            $io->table(['Page', 'Lost', 'Approximated', 'Text'], $rows);
        }
    }

    /**
     * @param list<array{space: string, page: string, page_id: string, title: string, outcome: string, from_revision: int|null, to_revision: int|null, fidelity: WikiConversionFidelity|null}> $entries
     */
    private function writeReport(SymfonyStyle $io, string $path, array $entries, bool $dryRun): bool
    {
        $pages  = [];
        $totals = ['pages' => count($entries), 'converted' => 0, 'skipped_markdown' => 0, 'skipped_empty' => 0, 'skipped_lossy' => 0, 'lossless' => 0, 'with_approximations' => 0, 'with_losses' => 0];
        foreach ($entries as $entry) {
            $outcome = match ($entry['outcome']) {
                'converted' => $dryRun ? 'would_convert' : 'converted',
                'markdown'  => 'skipped_markdown',
                'empty'     => 'skipped_empty',
                default     => 'skipped_lossy',
            };
            ++$totals[$outcome === 'would_convert' ? 'converted' : $outcome];
            $fidelity = $entry['fidelity'];
            if ($fidelity instanceof WikiConversionFidelity) {
                ++$totals[!$fidelity->isLossless() ? 'with_losses' : ($fidelity->approximated === [] ? 'lossless' : 'with_approximations')];
            }

            $pages[] = [
                'space'         => $entry['space'],
                'page'          => $entry['page'],
                'page_id'       => $entry['page_id'],
                'title'         => $entry['title'],
                'outcome'       => $outcome,
                'from_revision' => $entry['from_revision'],
                'to_revision'   => $entry['to_revision'],
            ] + ($fidelity instanceof WikiConversionFidelity ? ['lost' => (object) $fidelity->lost, 'approximated' => (object) $fidelity->approximated, 'text' => $fidelity->toArray()['text']] : []);
        }

        $json = json_encode([
            'format'       => self::REPORT_FORMAT,
            'version'      => self::REPORT_VERSION,
            'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'dry_run'      => $dryRun,
            'dialect'      => $this->markdownConverter->dialect ? 'milkdown' : 'gfm',
            'totals'       => $totals,
            'pages'        => $pages,
        ], JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        if (@file_put_contents($path, $json . "\n") === false) {
            $io->error(sprintf('The conversion report could not be written to "%s".', $path));

            return false;
        }

        $io->writeln(sprintf('Report written to %s.', $path));

        return true;
    }

    /**
     * @param list<array{fidelity: WikiConversionFidelity|null}> $entries
     */
    private function countLossy(array $entries): int
    {
        $lossy = 0;
        foreach ($entries as $entry) {
            if ($entry['fidelity'] instanceof WikiConversionFidelity && !$entry['fidelity']->isLossless()) {
                ++$lossy;
            }
        }

        return $lossy;
    }

    /**
     * @param array<string, int> $counts
     */
    private function codes(array $counts): string
    {
        $codes = [];
        foreach ($counts as $code => $count) {
            $codes[] = $count > 1 ? sprintf('%s ×%d', $code, $count) : $code;
        }

        return $codes === [] ? '-' : implode(', ', $codes);
    }
}
