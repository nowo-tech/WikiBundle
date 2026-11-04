<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use DateTimeImmutable;
use DateTimeZone;
use Nowo\WikiBundle\Dto\WikiExportReport;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiExportIndexWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;

final class WikiExportIndexWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wiki-index-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new WikiArchiveHelper())->removeDirectory($this->dir);
    }

    public function testWritesTheSpaceTitleDateAndNestedPageLinks(): void
    {
        $report        = new WikiExportReport(outputPath: $this->dir);
        $report->pages = [
            ['title' => 'Intro', 'path' => 'intro.md', 'depth' => 0],
            ['title' => "Set *up* [v2]\n(beta)", 'path' => 'Intro/Set up (v2)/Set up.md', 'depth' => 1],
        ];

        (new WikiExportIndexWriter())->write($this->space('Docs #1 & <team>'), $this->dir, $report, new DateTimeImmutable('2026-11-04 09:30', new DateTimeZone('Europe/Madrid')));

        self::assertSame(
            WikiExportIndexWriter::MARKER . "\n\n"
            . "# Docs \\#1 \\& \\<team\\>\n\n"
            . "Exported on 2026-11-04 08:30 UTC.\n\n"
            . "## Pages\n\n"
            . "- [Intro](intro.md)\n"
            . "  - [Set \\*up\\* \\[v2\\] \\(beta\\)](Intro/Set%20up%20%28v2%29/Set%20up.md)\n",
            file_get_contents($this->dir . '/README.md'),
        );
    }

    public function testSaysTheSpaceHasNoVisiblePagesWhenNothingWasExported(): void
    {
        (new WikiExportIndexWriter())->write($this->space('Empty'), $this->dir, new WikiExportReport(outputPath: $this->dir));

        $readme = (string) file_get_contents($this->dir . '/README.md');
        self::assertStringContainsString("# Empty\n", $readme);
        self::assertStringContainsString('This space has no pages visible to you.', $readme);
        self::assertStringNotContainsString('## Pages', $readme);
    }

    #[DataProvider('locales')]
    public function testTranslatesTheStringsInEveryShippedLocale(string $locale): void
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/NowoWikiBundle.' . $locale . '.yaml', $locale, 'NowoWikiBundle');

        $writer = new WikiExportIndexWriter($translator);
        $writer->write($this->space('Docs'), $this->dir, new WikiExportReport(outputPath: $this->dir), new DateTimeImmutable('2026-11-04 08:30 UTC'));
        $empty = (string) file_get_contents($this->dir . '/README.md');

        $report        = new WikiExportReport(outputPath: $this->dir);
        $report->pages = [['title' => 'Intro', 'path' => 'intro.md', 'depth' => 0]];
        $writer->write($this->space('Docs'), $this->dir, $report, new DateTimeImmutable('2026-11-04 08:30 UTC'));
        $listed = (string) file_get_contents($this->dir . '/README.md');

        self::assertStringContainsString('2026-11-04 08:30 UTC', $empty);
        self::assertStringNotContainsString('wiki.export.readme', $empty . $listed);
        self::assertStringNotContainsString('%date%', $empty);
        if ($locale !== 'en') {
            self::assertStringNotContainsString('This space has no pages visible to you.', $empty);
            self::assertStringNotContainsString('Exported on', $empty);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'] as $locale) {
            yield $locale => [$locale];
        }
    }

    public function testRecognisesOnlyItsOwnIndexFiles(): void
    {
        (new WikiExportIndexWriter())->write($this->space('Docs'), $this->dir, new WikiExportReport(outputPath: $this->dir));
        file_put_contents($this->dir . '/page.md', "# README\n");

        self::assertTrue(WikiExportIndexWriter::isIndexFile($this->dir . '/README.md'));
        self::assertFalse(WikiExportIndexWriter::isIndexFile($this->dir . '/page.md'));
        self::assertFalse(WikiExportIndexWriter::isIndexFile($this->dir . '/missing.md'));
    }

    public function testRefusesToReplaceAReadmeThatIsNotAnIndex(): void
    {
        file_put_contents($this->dir . '/README.md', "# My notes\n");
        $writer = new WikiExportIndexWriter();

        try {
            $writer->write($this->space('Docs'), $this->dir, new WikiExportReport(outputPath: $this->dir));
            self::fail('A foreign README.md must not be overwritten.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not a wiki export index', $exception->getMessage());
            self::assertStringNotContainsString($this->dir, $exception->getMessage());
        }
        self::assertSame("# My notes\n", file_get_contents($this->dir . '/README.md'));

        // The index of a previous export is replaced.
        unlink($this->dir . '/README.md');
        $writer->write($this->space('Old'), $this->dir, new WikiExportReport(outputPath: $this->dir));
        $writer->write($this->space('New'), $this->dir, new WikiExportReport(outputPath: $this->dir));
        self::assertStringContainsString('# New', (string) file_get_contents($this->dir . '/README.md'));
    }

    public function testFailsWithAPathFreeErrorWhenTheIndexCannotBeWritten(): void
    {
        $missing = $this->dir . '/missing';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write the export index (README.md).');
        (new WikiExportIndexWriter())->write($this->space('Docs'), $missing, new WikiExportReport(outputPath: $missing));
    }

    public function testInvalidUtf8TitlesStayOnOneLine(): void
    {
        // 1.15.1 (audit DV-I3): the /u pattern fails on invalid UTF-8; newlines must still be collapsed.
        self::assertSame("bad\xFF --- \\> quote", WikiExportIndexWriter::escape("bad\xFF\n\n---\n> quote"));
        self::assertStringNotContainsString("\n", WikiExportIndexWriter::escape("bad\xFF\r\n\t# x"));
    }

    private function space(string $name): WikiSpace
    {
        return new WikiSpace('docs', $name, WikiSpaceOwnerScope::Team, 'team-1');
    }
}
