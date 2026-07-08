<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

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

#[AsCommand(
    name: 'wiki:export',
    description: 'Export a wiki space to Outline or Notion compatible Markdown',
)]
final class WikiExportCommand extends Command
{
    public function __construct(
        private readonly WikiSpaceRepositoryInterface $spaceRepository,
        private readonly WikiDocumentExporter $documentExporter,
        private readonly WikiArchiveHelper $archiveHelper,
        private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('space', InputArgument::REQUIRED, 'Wiki space slug')
            ->addArgument('target', InputArgument::REQUIRED, 'Output directory (or .zip path with --zip)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'outline or notion', WikiInterchangeFormat::Outline->value)
            ->addOption('zip', null, InputOption::VALUE_NONE, 'Write a ZIP archive instead of a directory tree');
    }

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
        if (!$space instanceof \Nowo\WikiBundle\Entity\WikiSpace) {
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
            if (!mkdir($workingDir, 0777, true) && !is_dir($workingDir)) {
                $io->error('Unable to create temporary export directory.');

                return Command::FAILURE;
            }
            $cleanupDir = $workingDir;
        }

        $report = $this->documentExporter->export($space, $workingDir, $format);

        if ($asZip) {
            $this->archiveHelper->createZipFromDirectory($workingDir, $target);
            $report->outputPath = $target;
        }

        if ($cleanupDir !== null) {
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
