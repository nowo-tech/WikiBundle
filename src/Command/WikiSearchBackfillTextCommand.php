<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Search\WikiPlainText;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_numeric;
use function sprintf;

/**
 * Fills the revisions' `content_text` (1.16, {@see WikiPlainText::fromHtml()} of `content_html`), so the search
 * matches plain text instead of Markdown punctuation and directive attributes. Run it once after the 1.16 migration;
 * new revisions get their text on save. `--all` recomputes every revision (after a change of the plain-text rules),
 * `--dry-run` only counts. Batches are flushed and the entity manager cleared between them (bounded memory).
 */
#[AsCommand(
    name: 'wiki:search:backfill-text',
    description: 'Fill the plain-text search column (content_text) of wiki revisions written before 1.16',
)]
final class WikiSearchBackfillTextCommand extends Command
{
    /** Default number of revisions per batch. */
    public const BATCH = 200;

    public function __construct(private readonly EntityManagerInterface|WikiEntityManagerProvider $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Recompute every revision, not only those without a text')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Revisions per batch', (string) self::BATCH)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the revisions to fill, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $size  = $input->getOption('batch-size');
        $batch = is_numeric($size) ? (int) $size : 0;
        if ($batch < 1 || $batch > 10000) {
            $io->error('--batch-size must be an integer from 1 to 10000.');

            return Command::INVALID;
        }

        $em     = $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
        $all    = $input->getOption('all') === true;
        $dryRun = $input->getOption('dry-run') === true;
        $where  = $all ? '' : ' WHERE r.contentText IS NULL';

        /** @var int|string $total */
        $total = $em->createQuery('SELECT COUNT(r.id) FROM ' . WikiPageRevision::class . ' r' . $where)->getSingleScalarResult();
        if ($dryRun) {
            $io->success(sprintf('%d revision(s) to fill (dry run: nothing written).', (int) $total));

            return Command::SUCCESS;
        }

        $changed = 0;
        $seen    = 0;
        $lastId  = '';
        do {
            // Keyset pagination on the id: rows filled by this run leave the "IS NULL" set, so an offset would skip.
            /** @var list<WikiPageRevision> $revisions */
            $revisions = $em->createQuery('SELECT r FROM ' . WikiPageRevision::class . ' r WHERE r.id > :last' . ($all ? '' : ' AND r.contentText IS NULL') . ' ORDER BY r.id ASC')
                ->setParameter('last', $lastId)
                ->setMaxResults($batch)
                ->getResult();
            foreach ($revisions as $revision) {
                $changed += $revision->refreshContentText() ? 1 : 0;
                $lastId = $revision->getId();
            }
            $seen += count($revisions);
            $em->flush();
            // @igor-ignore - Bounded memory of this console run: the batch is detached, nothing is kept on the service.
            $em->clear();
        } while (count($revisions) === $batch);

        $io->success(sprintf('%d revision(s) read, %d text(s) written.', $seen, $changed));

        return Command::SUCCESS;
    }
}
