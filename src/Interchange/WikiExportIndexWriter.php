<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nowo\WikiBundle\Dto\WikiExportReport;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Contracts\Translation\TranslatorInterface;

use function addcslashes;
use function array_map;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function preg_replace;
use function rawurlencode;
use function str_repeat;
use function strlen;
use function strtr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Writes the {@code README.md} index at the root of a wiki export (1.15.1, Outline-style).
 *
 * The index holds the space title, the export date and the list of exported pages as relative links, or a line saying
 * the space has no pages visible to the exporter. It only lists the pages of the export report, so the titles of pages
 * the exporter may not read never appear. It also keeps the ZIP archive of an empty space non-empty (ZipArchive writes
 * no file for an archive without entries). The importer skips it through {@see self::isIndexFile()}.
 */
final readonly class WikiExportIndexWriter
{
    /** File name of the index at the export root. */
    public const FILENAME = 'README.md';

    /** First line of the index: lets the importer tell it apart from a page called README. */
    public const MARKER = '<!-- nowo-wiki:export-index -->';

    private const DOMAIN = 'NowoWikiBundle';

    /** English strings used when no translator is available. */
    private const FALLBACK = [
        'wiki.export.readme.exported_on' => 'Exported on %date%.',
        'wiki.export.readme.pages'       => 'Pages',
        'wiki.export.readme.empty'       => 'This space has no pages visible to you.',
    ];

    /**
     * Stores the translator used for the index strings.
     *
     * @param TranslatorInterface|null $translator translates the strings in the NowoWikiBundle domain (English without it)
     */
    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Writes the index of the exported pages into the export directory.
     *
     * @param WikiSpace $space exported space
     * @param string $targetDir export directory (the archive root)
     * @param WikiExportReport $report export report holding the exported pages
     * @param DateTimeInterface|null $exportedAt export date (now when null)
     */
    public function write(WikiSpace $space, string $targetDir, WikiExportReport $report, ?DateTimeInterface $exportedAt = null): void
    {
        $date  = DateTimeImmutable::createFromInterface($exportedAt ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));
        $lines = [
            self::MARKER,
            '',
            '# ' . self::escape($space->getName()),
            '',
            $this->trans('wiki.export.readme.exported_on', ['%date%' => $date->format('Y-m-d H:i') . ' UTC']),
            '',
        ];

        if ($report->pages === []) {
            $lines[] = $this->trans('wiki.export.readme.empty');
        } else {
            $lines[] = '## ' . $this->trans('wiki.export.readme.pages');
            $lines[] = '';
            foreach ($report->pages as $page) {
                $lines[] = str_repeat('  ', $page['depth']) . '- [' . self::escape($page['title']) . '](' . $this->linkTarget($page['path']) . ')';
            }
        }

        file_put_contents($targetDir . DIRECTORY_SEPARATOR . self::FILENAME, implode("\n", $lines) . "\n");
    }

    /**
     * Tells whether a Markdown file is an export index written by this class.
     *
     * @param string $path Markdown file path
     *
     * @return bool true when the file starts with {@see self::MARKER}
     */
    public static function isIndexFile(string $path): bool
    {
        return is_file($path) && file_get_contents($path, false, null, 0, strlen(self::MARKER)) === self::MARKER;
    }

    /**
     * Escapes text for Markdown link text and headings: collapses whitespace and backslash-escapes the
     * punctuation that could open links, emphasis, code, HTML or tables.
     *
     * @param string $text plain text (page or space title)
     *
     * @return string Markdown-safe text
     */
    public static function escape(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return addcslashes($text, '\\`*_[]()<>!|~#&');
    }

    /**
     * Relative link to an exported file, each path segment percent-encoded (spaces, brackets and parentheses in
     * Notion folder names would otherwise break the link).
     */
    private function linkTarget(string $relativePath): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        if ($this->translator instanceof TranslatorInterface) {
            return $this->translator->trans($key, $parameters, self::DOMAIN);
        }

        return strtr(self::FALLBACK[$key], $parameters);
    }
}
