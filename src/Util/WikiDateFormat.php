<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Throwable;

use function class_exists;
use function is_string;
use function preg_match;

/**
 * 1.31 (`web_ui.page_meta: card`): a date and time in the reader's locale — the ICU medium date and short time
 * (`IntlDateFormatter`), e.g. "Nov 11, 2026, 9:40 PM" in `en`, "11 nov 2026, 21:40" in `es` — in the date's own time
 * zone. Without the intl extension (or for a locale it cannot format, such as the polyfill's non-`en` locales) the
 * fixed `Y-m-d H:i` the scaffold meta always used. Pure function, no state (worker mode).
 */
final class WikiDateFormat
{
    /** The locale-free fallback (the 1.30 scaffold meta format). */
    public const FALLBACK = 'Y-m-d H:i';

    /** A BCP 47 / ICU locale id the formatter is given (anything else falls back). */
    private const LOCALE = '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,3}$/D';

    /**
     * @param DateTimeInterface $date the date to show
     * @param string|null $locale the reader's locale (the request locale), null or invalid: the fallback format
     */
    public static function medium(DateTimeInterface $date, ?string $locale): string
    {
        if (!is_string($locale) || preg_match(self::LOCALE, $locale) !== 1 || !class_exists(IntlDateFormatter::class)) {
            return $date->format(self::FALLBACK);
        }

        $zone = $date->getTimezone();
        try {
            $formatted = (new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, $zone instanceof DateTimeZone ? $zone : null))->format($date);
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            // The intl polyfill formats `en` only (a real extension does not throw here): which branch runs depends on
            // the extension, so neither environment covers both.
            $formatted = false;
        }
        // @codeCoverageIgnoreEnd

        return is_string($formatted) && $formatted !== '' ? $formatted : $date->format(self::FALLBACK);
    }
}
