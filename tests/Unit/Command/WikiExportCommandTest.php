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
use ZipArchive;

use function array_diff;
use function array_values;

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
            (new WikiArchiveHelper())->removeDirectory($target);
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

    public function testExportsEmptySpaceToZipArchiveWithReadme(): void
    {
        // 1.15.1: an empty space used to leave no archive behind (ZipArchive skips empty archives).
        $space  = new WikiSpace('empty', 'Empty space', WikiSpaceOwnerScope::Team, 'team-1');
        $target = sys_get_temp_dir() . '/wiki-export-cmd-' . bin2hex(random_bytes(4)) . '.zip';
        $before = glob(sys_get_temp_dir() . '/wiki-export-*') ?: [];

        try {
            $tester = new CommandTester($this->command(space: $space, slug: 'empty'));
            $status = $tester->execute(['space' => 'empty', 'target' => $target, '--zip' => true]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertStringContainsString('Exported 0 pages', $tester->getDisplay());
            $zip = new ZipArchive();
            self::assertTrue($zip->open($target));
            self::assertSame(1, $zip->numFiles);
            $readme = (string) $zip->getFromName('README.md');
            $zip->close();
            self::assertStringContainsString('# Empty space', $readme);
            self::assertStringContainsString('This space has no pages visible to you.', $readme);
            self::assertSame([$target], array_values(array_diff(glob(sys_get_temp_dir() . '/wiki-export-*') ?: [], $before)));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testExportsEmptySpaceToDirectoryWithReadme(): void
    {
        $space  = new WikiSpace('empty', 'Empty space', WikiSpaceOwnerScope::Team, 'team-1');
        $target = sys_get_temp_dir() . '/wiki-export-cmd-' . bin2hex(random_bytes(4));

        try {
            $tester = new CommandTester($this->command(space: $space, slug: 'empty'));
            $status = $tester->execute(['space' => 'empty', 'target' => $target]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertSame(['README.md'], array_values(array_diff(scandir($target) ?: [], ['.', '..'])));
        } finally {
            (new WikiArchiveHelper())->removeDirectory($target);
        }
    }

    public function testFailedZipExportReportsTheErrorAndLeavesNoTemporaryFiles(): void
    {
        // A directory is not a writable ZIP path: ZipArchive::open() fails after the pages were exported.
        $target = sys_get_temp_dir() . '/wiki-cmd-target-' . bin2hex(random_bytes(4));
        mkdir($target);
        $before = glob(sys_get_temp_dir() . '/wiki-export-*') ?: [];

        try {
            $tester = new CommandTester($this->command());
            $status = $tester->execute(['space' => 'docs', 'target' => $target, '--zip' => true]);

            self::assertSame(Command::FAILURE, $status);
            self::assertStringContainsString('Unable to create ZIP archive.', $tester->getDisplay());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-export-*') ?: []);
        } finally {
            rmdir($target);
        }
    }

    public function testRefusesADirectoryWithAForeignReadme(): void
    {
        // 1.15.1 (audit DV-L1): a README.md that is not a wiki export index is never overwritten.
        $target = sys_get_temp_dir() . '/wiki-cmd-target-' . bin2hex(random_bytes(4));
        mkdir($target);
        file_put_contents($target . '/README.md', "# Mine\n");

        try {
            $tester = new CommandTester($this->command());
            $status = $tester->execute(['space' => 'docs', 'target' => $target]);

            self::assertSame(Command::FAILURE, $status);
            self::assertStringContainsString('not a wiki export index', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
            self::assertSame(['README.md'], array_values(array_diff(scandir($target) ?: [], ['.', '..'])));
            self::assertSame("# Mine\n", file_get_contents($target . '/README.md'));
        } finally {
            (new WikiArchiveHelper())->removeDirectory($target);
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
            (new WikiArchiveHelper())->removeDirectory($target);
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
        string $slug = 'docs',
    ): WikiExportCommand {
        $space ??= $this->spaceWithPage();

        $page = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturnCallback(static fn (WikiSpace $s): array => $s->getSlug() === 'docs' ? [$page] : []);

        $spaceRepository ??= $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaceRepository->method('findFirstBySlug')->willReturnCallback(static fn (string $requested): ?WikiSpace => $requested === $slug ? $space : null);

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
