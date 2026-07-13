<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Nowo\WikiBundle\Command\WikiImportCommand;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class WikiImportCommandTest extends TestCase
{
    public function testFailsWhenDisabled(): void
    {
        $tester = new CommandTester($this->command(enabled: false));
        $status = $tester->execute(['space' => 'docs', 'source' => '/tmp/source']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testRequiresAuthorId(): void
    {
        $tester = new CommandTester($this->command());
        $status = $tester->execute(['space' => 'docs', 'source' => '/tmp/source']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('--author-id', $tester->getDisplay());
    }

    public function testFailsWhenSpaceNotFound(): void
    {
        $spaceRepository = $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaceRepository->method('findFirstBySlug')->willReturn(null);

        $tester = new CommandTester($this->command(spaceRepository: $spaceRepository));
        $status = $tester->execute([
            'space'       => 'missing',
            'source'      => '/tmp/source',
            '--author-id' => 'user-1',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testImportsMarkdownDirectory(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-cmd-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        try {
            $tester = new CommandTester($this->command());
            $status = $tester->execute([
                'space'       => 'docs',
                'source'      => $root,
                '--author-id' => 'user-1',
                '--format'    => WikiInterchangeFormat::Outline->value,
            ]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertStringContainsString('1 created', $tester->getDisplay());
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testReturnsFailureWhenImportHasErrors(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-cmd-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/bad.md', <<<'MD'
---
title: Bad
wiki_slug: invalid slug
---
Body
MD);

        try {
            $tester = new CommandTester($this->command());
            $status = $tester->execute([
                'space'       => 'docs',
                'source'      => $root,
                '--author-id' => 'user-1',
            ]);

            self::assertSame(Command::FAILURE, $status);
            self::assertStringContainsString('failed', strtolower($tester->getDisplay()));
        } finally {
            unlink($root . '/bad.md');
            rmdir($root);
        }
    }

    private function command(
        bool $enabled = true,
        ?WikiSpaceRepositoryInterface $spaceRepository = null,
    ): WikiImportCommand {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');

        $spaceRepository ??= $this->createMock(WikiSpaceRepositoryInterface::class);
        $spaceRepository->method('findFirstBySlug')->willReturnCallback(static fn (string $slug): ?WikiSpace => $slug === 'docs' ? $space : null);

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepository->method('save');

        $revisionRepository = $this->createMock(\Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepository->method('save');

        $pageService = new WikiPageService(
            $pageRepository,
            $revisionRepository,
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        $authorResolver = new WikiAuthorResolver(
            $this->authorEntityManager(new TestUser()),
            TestUser::class,
        );

        return new WikiImportCommand(
            $spaceRepository,
            new WikiDocumentImporter(
                new WikiArchiveHelper(),
                new WikiFormatDetector(new WikiFrontMatterParser()),
                new WikiDocumentTreeReader(new WikiFrontMatterParser()),
                new WikiMarkdownConverter(),
                $pageRepository,
                $pageService,
                new WikiSlugger(),
            ),
            $authorResolver,
            $enabled,
        );
    }

    private function authorEntityManager(object $user): \Doctrine\ORM\EntityManagerInterface
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->willReturn($user);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }
}
