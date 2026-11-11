<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Util;

use DateTimeImmutable;
use DateTimeZone;
use Nowo\WikiBundle\Util\WikiDateFormat;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function preg_replace;

/**
 * 1.31 (web_ui.page_meta: card): the date in the reader's locale, the fixed format otherwise.
 */
final class WikiDateFormatTest extends TestCase
{
    public function testTheMediumDateAndShortTimeOfTheLocaleInTheDateZone(): void
    {
        $date = new DateTimeImmutable('2026-11-11 21:40', new DateTimeZone('Europe/Madrid'));

        // ICU writes a (narrow) no-break space before "PM"; the polyfill a plain space.
        self::assertSame('Nov 11, 2026, 9:40 PM', preg_replace('/\s+/u', ' ', WikiDateFormat::medium($date, 'en')));
    }

    public function testTheFixedFormatWithoutAUsableLocale(): void
    {
        $date = new DateTimeImmutable('2026-11-11 21:40', new DateTimeZone('UTC'));

        self::assertSame('2026-11-11 21:40', WikiDateFormat::medium($date, null));
        self::assertSame('2026-11-11 21:40', WikiDateFormat::medium($date, ''));
        self::assertSame('2026-11-11 21:40', WikiDateFormat::medium($date, 'en"><script>'));
    }

    public function testAnotherLocale(): void
    {
        $date = new DateTimeImmutable('2026-11-11 21:40', new DateTimeZone('UTC'));
        $text = WikiDateFormat::medium($date, 'es_ES');

        // The intl extension writes the Spanish date (24-hour time); the polyfill (en only) falls back to the fixed format.
        if (extension_loaded('intl')) {
            self::assertStringContainsString('21:40', $text);
            self::assertNotSame('2026-11-11 21:40', $text);
        } else {
            self::assertSame('2026-11-11 21:40', $text);
        }
    }
}
