<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Command\WikiCollabPurgeCommand;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WikiCollabPurgeCommandTest extends TestCase
{
    public function testPurgesIdleLogs(): void
    {
        $store = new InMemoryCollabStateStore();
        $store->append('idle', 'AQ==');
        $store->pages['idle']['updated'] = new DateTimeImmutable('-30 days');
        $store->append('live', 'AQ==');
        $tester = new CommandTester(new WikiCollabPurgeCommand($store));

        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '7']));
        self::assertStringContainsString('1 co-editing log(s) purged.', $tester->getDisplay());
        self::assertArrayHasKey('live', $store->pages);
        self::assertArrayNotHasKey('idle', $store->pages);

        self::assertSame(Command::FAILURE, $tester->execute(['--older-than' => 'week']));
        self::assertStringContainsString('whole number of days', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--older-than' => '0']), 'CW-I10: never a log in use');
        self::assertStringContainsString('at least 1', $tester->getDisplay());
    }

    public function testPurgesThroughTheServiceSoLiveEditorsReload(): void
    {
        $store = new InMemoryCollabStateStore();
        $store->append('idle', 'AQ==');
        $store->pages['idle']['updated'] = new DateTimeImmutable('-30 days');
        $published                       = [];
        $publisher                       = new class($published) implements WikiCollabPublisherInterface {
            /** @param list<string> $published */
            public function __construct(private array &$published)
            {
            }

            public function publish(string $topic, string $message): void
            {
                $this->published[] = $topic;
            }
        };
        $service = new WikiCollabService($store, $publisher, $this->createMock(WikiCollabSubscriberAuthorizerInterface::class), ['topic_prefix' => 't:']);
        $tester  = new CommandTester(new WikiCollabPurgeCommand($store, $service));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(['t:idle'], $published);
    }

    public function testNothingToDoWithoutCollaboration(): void
    {
        $tester = new CommandTester(new WikiCollabPurgeCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('nothing to purge', $tester->getDisplay());
    }
}
