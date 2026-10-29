<?php

declare(strict_types=1);

/**
 * Validates the bundle translation catalogues (REQ-MAKE-004 / REQ-I18N-002 rule 4).
 *
 * - every *.yaml file under the translations directory must parse;
 * - the seven required locales (en, es, it, fr, pt, de, nl) must exist for each domain;
 * - each locale must hold exactly the flattened key set of the "en" reference (no missing / extra keys).
 *
 * Exit code 0 when everything is valid, 1 otherwise.
 *
 * Usage: php scripts/check-translation-parity.php [translations-dir]
 */

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

const REQUIRED_LOCALES = ['en', 'es', 'it', 'fr', 'pt', 'de', 'nl'];

/**
 * Flattens a nested catalogue into dot-separated keys.
 *
 * @param array<mixed> $tree
 *
 * @return list<string>
 */
function flattenKeys(array $tree, string $prefix = ''): array
{
    $keys = [];
    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            array_push($keys, ...flattenKeys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

$dir    = rtrim($argv[1] ?? dirname(__DIR__) . '/src/Resources/translations', '/');
$errors = [];
/** @var array<string, array<string, list<string>>> $catalogues domain => locale => keys */
$catalogues = [];

$files = glob($dir . '/*.yaml') ?: [];
if ($files === []) {
    fwrite(\STDERR, "No YAML translation files found in {$dir}\n");
    exit(1);
}

foreach ($files as $file) {
    if (!preg_match('/^(?<domain>[^.]+)\.(?<locale>[A-Za-z_]+)\.yaml$/', basename($file), $m)) {
        $errors[] = sprintf('%s: file name must be <Domain>.<locale>.yaml', basename($file));
        continue;
    }

    try {
        $parsed = Yaml::parseFile($file);
    } catch (ParseException $exception) {
        $errors[] = sprintf('%s: %s', basename($file), $exception->getMessage());
        continue;
    }

    $catalogues[$m['domain']][$m['locale']] = flattenKeys(is_array($parsed) ? $parsed : []);
}

foreach ($catalogues as $domain => $locales) {
    if (!isset($locales['en'])) {
        $errors[] = sprintf('%s: reference locale "en" is missing', $domain);
        continue;
    }

    $reference = $locales['en'];
    foreach (REQUIRED_LOCALES as $locale) {
        if (!isset($locales[$locale])) {
            $errors[] = sprintf('%s: required locale "%s" is missing', $domain, $locale);
        }
    }

    foreach ($locales as $locale => $keys) {
        foreach (array_diff($reference, $keys) as $missing) {
            $errors[] = sprintf('%s.%s: missing key "%s"', $domain, $locale, $missing);
        }
        foreach (array_diff($keys, $reference) as $extra) {
            $errors[] = sprintf('%s.%s: extra key "%s" (not in en)', $domain, $locale, $extra);
        }
    }
}

if ($errors !== []) {
    fwrite(\STDERR, "Translation validation failed:\n  - " . implode("\n  - ", $errors) . "\n");
    exit(1);
}

$summary = [];
foreach ($catalogues as $domain => $locales) {
    $summary[] = sprintf('%s: %d locales x %d keys', $domain, count($locales), count($locales['en']));
}
echo 'Translations OK (' . implode('; ', $summary) . ")\n";
