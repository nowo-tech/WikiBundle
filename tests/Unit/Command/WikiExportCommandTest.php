<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Nowo\WikiBundle\Command\WikiExportCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WikiExportCommandTest extends TestCase
{
    public function testFailsWhenDisabled(): void
    {
        $tester = new CommandTester($this->command(enabled: false));
        $status = $tester->execute(['space' => 'docs', 'target' => '/tmp/out']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testFailsWhenSpaceNotFound(): void
    {
        $spaceRepository = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaceRepository->method('findFirstBySlug')->willReturn(null);

        $tester = new CommandTester($this->command(spaceRepository: $spaceRepository));
        $status = $tester->execute(['space' => 'missing', 'target' => sys_get_temp_dir() . '/wiki-export-test']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testExportsToDirectory(): void
    {
        $space  = $this->spaceWithPage();
        $target = sys_get_temp_dir() . '/wiki-export-cmd-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            $tester = new CommandTester($this->command(space: $space));
            $status = $tester->execute(['space' => 'docs', 'target' => $target]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertFileExists($target . '/intro.md');
            self::assertStringContainsString('Exported 1 pages', $tester->getDisplay());
        } finally {
            if (is_file($target . '/intro.md')) {
                unlink($target . '/intro.md');
            }
            if (is_dir($target)) {
                rmdir($target);
            }
        }
    }

    public function testExportsZipArchive(): void
    {
        $space  = $this->spaceWithPage();
        $target = sys_get_temp_dir() . '/wiki-export-cmd-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $tester = new CommandTester($this->command(space: $space));
            $status = $tester->execute([
                'space'  => 'docs',
                'target' => $target,
                '--zip'  => true,
            ]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertFileExists($target);
            self::assertStringContainsString('outline', $tester->getDisplay());
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testAutoFormatFallsBackToOutline(): void
    {
        $space  = $this->spaceWithPage();
        $target = sys_get_temp_dir() . '/wiki-export-cmd-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            $tester = new CommandTester($this->command(space: $space));
            $status = $tester->execute([
                'space'    => 'docs',
                'target'   => $target,
                '--format' => WikiInterchangeFormat::Auto->value,
            ]);

            self::assertSame(Command::SUCCESS, $status);
        } finally {
            if (is_file($target . '/intro.md')) {
                unlink($target . '/intro.md');
            }
            if (is_dir($target)) {
                rmdir($target);
            }
        }
    }

    private function spaceWithPage(): WikiSpace
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        return $space;
    }

    private function command(
        bool $enabled = true,
        ?WikiSpace $space = null,
        ?WikiSpaceRepositoryInterface $spaceRepository = null,
    ): WikiExportCommand {
        $space ??= $this->spaceWithPage();

        $page = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturnCallback(static fn (WikiSpace $s): array => $s->getSlug() === $space->getSlug() ? [$page] : []);

        $spaceRepository ??= $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaceRepository->method('findFirstBySlug')->willReturnCallback(static fn (string $slug): ?WikiSpace => $slug === 'docs' ? $space : null);

        return new WikiExportCommand(
            $spaceRepository,
            new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ),
            new WikiArchiveHelper(),
            $enabled,
        );
    }
}
