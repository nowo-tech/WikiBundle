#!/usr/bin/env bash
# CI helper (1.46.0): SearchKit (nowo-tech/search-kit-bundle, PHP >= 8.3) is an optional dependency of the wiki's
# SearchKit group (src/Search/SearchKit, nowo_wiki.search_kit_provider). It cannot be in composer.json's require-dev: the
# bundle supports PHP 8.2 and the PHP 8.2 jobs (and composer.json's platform 8.2.0) could not resolve it. On PHP >= 8.3
# this adds it to require-dev and sets the platform to the running PHP, so those jobs test, analyse and cover the group;
# on PHP 8.2 it does nothing (the group is not registered, its tests are skipped and PHPStan leaves it out).
# Run it before `composer update` / `composer install` (an install then resolves again: the lock no longer matches).
set -euo pipefail

CONSTRAINT="${SEARCH_KIT_CONSTRAINT:-^1.10.1}"

if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
  echo "Adding nowo-tech/search-kit-bundle ${CONSTRAINT} to require-dev (PHP $(php -r 'echo PHP_VERSION;'))"
  jq --arg c "${CONSTRAINT}" '."require-dev"["nowo-tech/search-kit-bundle"] = $c' composer.json > composer.json.tmp
  mv composer.json.tmp composer.json
  composer config platform.php "$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . ".99";')"
else
  echo "PHP < 8.3: SearchKit is not installed; the wiki's SearchKit group is skipped"
fi
