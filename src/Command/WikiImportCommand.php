<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function sprintf;

/**
 * Console command {@code wiki:import}: imports Outline or Notion Markdown exports into a wiki space.
 */
#[AsCommand(
    name: 'wiki:import',
    description: 'Import Markdown documents from an Outline or Notion export (directory or ZIP)',
)]
final class WikiImportCommand extends Command
{
    /**
     * Wires the repositories and services used by the import.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository repository used to find the target space
     * @param WikiDocumentImporter $documentImporter importer that writes pages and revisions
     * @param WikiAuthorResolver $authorResolver resolves the {@code --author-id} user
     * @param bool $enabled whether import/export is enabled in configuration
     */
    public function __construct(
        private readonly WikiSpaceRepositoryInterface $spaceRepository,
        private readonly WikiDocumentImporter $documentImporter,
        private readonly WikiAuthorResolver $authorResolver,
        private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    /**
     * Declares the space and source arguments and the format, author, update and dry-run options.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('space', InputArgument::REQUIRED, 'Wiki space slug')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to export directory or ZIP file')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'auto, outline, or notion', WikiInterchangeFormat::Auto->value)
            ->addOption('author-id', null, InputOption::VALUE_REQUIRED, 'User id or email recorded as revision author')
            ->addOption('update-existing', null, InputOption::VALUE_NONE, 'Update pages when slug already exists')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and report without writing to the database');
    }

    /**
     * Runs the import into the given space and prints the import report.
     *
     * @param InputInterface $input console input
     * @param OutputInterface $output console output
     *
     * @return int Command::FAILURE when disabled, the author or space is missing, or any document failed; Command::SUCCESS otherwise
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->enabled) {
            $output->writeln('<error>Wiki import/export is disabled (nowo_wiki.import_export.enabled).</error>');

            return Command::FAILURE;
        }

        $io        = new SymfonyStyle($input, $output);
        $spaceSlug = (string) $input->getArgument('space');
        $source    = (string) $input->getArgument('source');
        $authorId  = $input->getOption('author-id');

        if (!is_string($authorId) || $authorId === '') {
            $io->error('The --author-id option is required for CLI imports.');

            return Command::FAILURE;
        }

        $space = $this->spaceRepository->findFirstBySlug($spaceSlug);
        if (!$space instanceof WikiSpace) {
            $io->error(sprintf('Space "%s" not found.', $spaceSlug));

            return Command::FAILURE;
        }

        $format = WikiInterchangeFormat::tryFrom((string) $input->getOption('format')) ?? WikiInterchangeFormat::Auto;
        $author = $this->authorResolver->resolveByIdentifier($authorId);

        $report = $this->documentImporter->import(
            $space,
            $source,
            $format,
            $author,
            (bool) $input->getOption('update-existing'),
            (bool) $input->getOption('dry-run'),
        );

        $io->success(sprintf(
            'Import finished: %d created, %d updated, %d skipped, %d failed.',
            $report->created,
            $report->updated,
            $report->skipped,
            $report->failed,
        ));

        foreach ($report->messages as $message) {
            $io->writeln(' - ' . $message);
        }

        return $report->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
