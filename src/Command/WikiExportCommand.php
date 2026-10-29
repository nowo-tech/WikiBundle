<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Console command {@code wiki:export}: exports a wiki space as Outline or Notion compatible Markdown.
 */
#[AsCommand(
    name: 'wiki:export',
    description: 'Export a wiki space to Outline or Notion compatible Markdown',
)]
final class WikiExportCommand extends Command
{
    /**
     * Wires the repositories and services used by the export.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository repository used to find the space
     * @param WikiDocumentExporter $documentExporter exporter that writes the Markdown tree
     * @param WikiArchiveHelper $archiveHelper ZIP and temporary directory helper
     * @param bool $enabled whether import/export is enabled in configuration
     */
    public function __construct(
        private readonly WikiSpaceRepositoryInterface $spaceRepository,
        private readonly WikiDocumentExporter $documentExporter,
        private readonly WikiArchiveHelper $archiveHelper,
        private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    /**
     * Declares the space and target arguments and the format and zip options.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('space', InputArgument::REQUIRED, 'Wiki space slug')
            ->addArgument('target', InputArgument::REQUIRED, 'Output directory (or .zip path with --zip)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'outline or notion', WikiInterchangeFormat::Outline->value)
            ->addOption('zip', null, InputOption::VALUE_NONE, 'Write a ZIP archive instead of a directory tree');
    }

    /**
     * Exports the space to a directory, or to a ZIP archive built from a temporary directory with {@code --zip}.
     *
     * @param InputInterface $input console input
     * @param OutputInterface $output console output
     *
     * @return int Command::FAILURE when disabled or the space is missing; Command::SUCCESS otherwise
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->enabled) {
            $output->writeln('<error>Wiki import/export is disabled (nowo_wiki.import_export.enabled).</error>');

            return Command::FAILURE;
        }

        $io        = new SymfonyStyle($input, $output);
        $spaceSlug = (string) $input->getArgument('space');
        $target    = (string) $input->getArgument('target');
        $asZip     = (bool) $input->getOption('zip');

        $space = $this->spaceRepository->findFirstBySlug($spaceSlug);
        if (!$space instanceof WikiSpace) {
            $io->error(sprintf('Space "%s" not found.', $spaceSlug));

            return Command::FAILURE;
        }

        $format = WikiInterchangeFormat::tryFrom((string) $input->getOption('format')) ?? WikiInterchangeFormat::Outline;
        if ($format === WikiInterchangeFormat::Auto) {
            $format = WikiInterchangeFormat::Outline;
        }

        $workingDir = $target;
        $cleanupDir = null;

        if ($asZip) {
            $workingDir = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(8));
            // @codeCoverageIgnoreStart
            if (!mkdir($workingDir, 0700, true) && !is_dir($workingDir)) {
                $io->error('Unable to create temporary export directory.');

                return Command::FAILURE;
            }
            // @codeCoverageIgnoreEnd
            $cleanupDir = $workingDir;
        }

        $report = $this->documentExporter->export($space, $workingDir, $format);

        if ($asZip) {
            $this->archiveHelper->createZipFromDirectory($workingDir, $target);
            $report->outputPath = $target;
        }

        if ($cleanupDir !== null) {
            // @igor-ignore - Console command; not executed in FrankenPHP worker HTTP requests.
            $this->archiveHelper->removeDirectory($cleanupDir);
        }

        $io->success(sprintf(
            'Exported %d pages to %s (%s format).',
            $report->pagesExported,
            $report->outputPath,
            $format->value,
        ));

        return Command::SUCCESS;
    }
}
