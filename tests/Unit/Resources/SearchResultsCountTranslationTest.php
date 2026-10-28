<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

use function dirname;

/**
 * The search page passes `%count%` to `wiki.search.results_count`: the catalogues use Symfony's plural syntax (they
 * are not `+intl-icu` files, so an ICU message would be printed raw).
 */
final class SearchResultsCountTranslationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function cases(): iterable
    {
        yield 'en one' => ['en', 1, '1 result'];
        yield 'en many' => ['en', 3, '3 results'];
        yield 'en none' => ['en', 0, '0 results'];
        yield 'es many' => ['es', 2, '2 resultados'];
        yield 'de one' => ['de', 1, '1 Ergebnis'];
    }

    #[DataProvider('cases')]
    public function testResultsCountIsPluralised(string $locale, int $count, string $expected): void
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/NowoWikiBundle.' . $locale . '.yaml', $locale, 'NowoWikiBundle');

        self::assertSame($expected, $translator->trans('wiki.search.results_count', ['%count%' => $count], 'NowoWikiBundle'));
    }
}
