<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function array_values;
use function basename;
use function glob;
use function in_array;
use function is_array;
use function mb_strlen;
use function preg_match;
use function preg_replace;
use function sort;
use function sprintf;

/**
 * Guards against untranslated strings: in every non-en catalogue (all domains) a key must exist, and a value identical to
 * the English one (any length, ignoring values without letters once placeholders are removed) must be listed in
 * ALLOWED_IDENTICAL for that locale. A non-es value identical to the Spanish one (longer than 12 characters, not English)
 * must be listed in ALLOWED_SAME_AS_SPANISH. An allow-list entry that no longer applies is reported as stale.
 */
final class TranslationCatalogueTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../src/Resources/translations';

    /**
     * Keys that are correctly identical to English in a locale (brands, product and technical names, codes, loanwords
     * and cognates), per locale.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_IDENTICAL = [
        'de' => [
            'wiki.diff.range',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.app',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.history.revision',
            'wiki.import.format',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.name',
            'wiki.index.slug',
            'wiki.page.meta_revision',
            'wiki.page.revision',
            'wiki.search_kit.group',
            'wiki.space.name',
            'wiki.title',
            'wiki.tree.move_position',
            'wiki_page_form.icon.placeholder',
        ],
        'es' => [
            'wiki.diff.range',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.app',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.slug',
            'wiki.page.meta_revision',
            'wiki.search_kit.group',
            'wiki.title',
            'wiki_page_form.icon.placeholder',
        ],
        'fr' => [
            'wiki.ai.sources_title',
            'wiki.export.readme.pages',
            'wiki.focus.tree',
            'wiki.history.provenance',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.import.format',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.actions',
            'wiki.index.slug',
            'wiki.page.file_image',
            'wiki.page.meta_revision',
            'wiki.pagination.label',
            'wiki.search.result_title',
            'wiki.search_kit.group',
            'wiki.space.pages',
            'wiki.tabs.page',
            'wiki.title',
            'wiki.trash.purge_pages',
            'wiki.tree.move_position',
            'wiki_page_form.icon.placeholder',
        ],
        'it' => [
            'wiki.diff.range',
            'wiki.history.provenance',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.app',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.slug',
            'wiki.page.file',
            'wiki.page.meta_revision',
            'wiki.search_kit.group',
            'wiki.title',
            'wiki_page_form.icon.placeholder',
        ],
        'nl' => [
            'wiki.comments.open_suffix',
            'wiki.diff.range',
            'wiki.history.provenance',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.app',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.slug',
            'wiki.page.meta_revision',
            'wiki.search_kit.group',
            'wiki.title',
            'wiki_page_form.icon.placeholder',
        ],
        'pt' => [
            'wiki.diff.range',
            'wiki.history.provenance',
            'wiki.history.provenance_channel.api',
            'wiki.history.provenance_channel.app',
            'wiki.history.provenance_channel.mcp',
            'wiki.history.provenance_channel.web',
            'wiki.import.format_notion',
            'wiki.import.format_outline',
            'wiki.index.slug',
            'wiki.page.meta_revision',
            'wiki.search_kit.group',
            'wiki.title',
            'wiki_page_form.icon.placeholder',
        ],
    ];

    /**
     * Keys whose non-es value legitimately equals the Spanish one (longer than 12 characters, different from English):
     * words and phrases that are genuinely the same in Spanish and the locale, per locale.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_SAME_AS_SPANISH = [
        'pt' => [
            'wiki.children.count',
            'wiki.children.count_more',
            'wiki.comments.new_label',
            'wiki.export.notion',
            'wiki.export.outline',
            'wiki.new_page.context_child',
            'wiki.page.edit_title',
            'wiki.page.unarchive',
            'wiki.page_nav.prev',
            'wiki.page_nav.prev_aria',
            'wiki.pagination.page_of',
            'wiki.search.results_count',
            'wiki.tree.move_parent',
            'wiki.tree.move_title',
            'wiki.tree.toggle_children',
            'wiki.tree_menu.icon_context',
        ],
    ];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        $files = glob(self::DIR . '/*.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (preg_match('/^(.+)\.([a-z]{2})\.yaml$/', basename($file), $m) === 1 && $m[2] !== 'en') {
                yield basename($file) => [$m[1], $m[2]];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testLocaleHasEveryEnglishKey(string $domain, string $locale): void
    {
        $missing = array_values(array_diff(array_keys($this->load($domain, 'en')), array_keys($this->load($domain, $locale))));

        self::assertSame([], $missing, sprintf('Keys of %s.en.yaml missing in %s.%s.yaml', $domain, $domain, $locale));
    }

    #[DataProvider('catalogues')]
    public function testNoUntranslatedEnglishValues(string $domain, string $locale): void
    {
        $english = $this->load($domain, 'en');
        $target  = $this->load($domain, $locale);
        $allowed = self::ALLOWED_IDENTICAL[$locale] ?? [];
        $gaps    = [];

        foreach ($english as $key => $value) {
            if (!isset($target[$key]) || $target[$key] !== $value || in_array($key, $allowed, true)) {
                continue;
            }

            if (preg_match('/[^\W\d_]/u', (string) preg_replace('/%[^%\s]+%|\{[^}]+\}/', '', $value)) === 1) {
                $gaps[] = $key;
            }
        }

        self::assertSame([], $gaps, sprintf('Values identical to English in %s.%s.yaml (translate or allow-list)', $domain, $locale));
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonSpanishCatalogues(): iterable
    {
        foreach (self::catalogues() as $name => [$domain, $locale]) {
            if ($locale !== 'es') {
                yield $name => [$domain, $locale];
            }
        }
    }

    #[DataProvider('nonSpanishCatalogues')]
    public function testNoSpanishLeftInOtherLocales(string $domain, string $locale): void
    {
        $english = $this->load($domain, 'en');
        $spanish = $this->load($domain, 'es');
        $target  = $this->load($domain, $locale);
        $allowed = self::ALLOWED_SAME_AS_SPANISH[$locale] ?? [];
        $leaks   = [];

        foreach ($spanish as $key => $value) {
            if (!isset($target[$key]) || $target[$key] !== $value || $value === ($english[$key] ?? null) || mb_strlen($value) <= 12 || in_array($key, $allowed, true)) {
                continue;
            }

            $leaks[] = $key;
        }

        self::assertSame([], $leaks, sprintf('Values identical to Spanish in %s.%s.yaml (translate or allow-list)', $domain, $locale));
    }

    public function testAllowListHasNoStaleEntries(): void
    {
        $english = [];
        $locales = [];

        foreach (self::catalogues() as [$domain, $locale]) {
            $english += $this->load($domain, 'en');
            $locales[$locale] = ($locales[$locale] ?? []) + $this->load($domain, $locale);
        }

        $stale = [];

        foreach (self::ALLOWED_IDENTICAL as $locale => $keys) {
            foreach ($keys as $key) {
                if (!isset($english[$key]) || ($locales[$locale][$key] ?? null) !== $english[$key]) {
                    $stale[] = $locale . ':' . $key;
                }
            }
        }

        self::assertSame([], $stale, 'Allow-list entries that no longer exist or are no longer identical to English');
    }

    public function testSpanishAllowListHasNoStaleEntries(): void
    {
        $english = [];
        $spanish = [];
        $locales = [];

        foreach (self::catalogues() as [$domain, $locale]) {
            $english += $this->load($domain, 'en');
            $spanish += $this->load($domain, 'es');
            $locales[$locale] = ($locales[$locale] ?? []) + $this->load($domain, $locale);
        }

        $stale = [];

        foreach (self::ALLOWED_SAME_AS_SPANISH as $locale => $keys) {
            foreach ($keys as $key) {
                $value = $spanish[$key] ?? null;

                if ($value === null || ($locales[$locale][$key] ?? null) !== $value || $value === ($english[$key] ?? null) || mb_strlen($value) <= 12) {
                    $stale[] = $locale . ':' . $key;
                }
            }
        }

        self::assertSame([], $stale, 'Spanish allow-list entries that no longer exist or no longer equal the Spanish value');
    }

    /** @return array<string, string> */
    private function load(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(sprintf('%s/%s.%s.yaml', self::DIR, $domain, $locale));

        return $this->flatten(is_array($data) ? $data : []);
    }

    /**
     * @param array<mixed> $tree
     *
     * @return array<string, string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $out = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $out += $this->flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }
}
