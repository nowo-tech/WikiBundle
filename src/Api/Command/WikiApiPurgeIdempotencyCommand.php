<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Hook\WikiApiIdempotencyStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 1.24 (spec 218): drops the idempotency answers older than 24 hours (they may hold page text). Schedule it; claims purge
 * opportunistically too.
 */
#[AsCommand(name: 'nowo:wiki:api:purge-idempotency', description: 'Delete expired Wiki API idempotency answers.')]
final class WikiApiPurgeIdempotencyCommand extends Command
{
    public function __construct(private readonly WikiApiIdempotencyStoreInterface $store)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Deleted ' . $this->store->purgeExpired(new DateTimeImmutable()) . ' expired answer(s).');

        return Command::SUCCESS;
    }
}
