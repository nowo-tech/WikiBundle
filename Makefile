# Wiki Bundle - Development
SHELL := /bin/bash
.PHONY: help up down build clean shell install test test-coverage coverage-php-percent cs-check cs-fix qa assets assets-build assets-watch assets-test test-ts test-coverage-100 setup-hooks twig-lint ensure-up rector rector-dry phpstan igor release-check release-check-demos demo-smoke composer-sync update validate validate-translations check-no-cursor-coauthor check-open-prs strip-cursor-coauthor-from-history check-twig-extra check-no-raw-html-form

COMPOSE_FILE ?= docker-compose.yml
# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ (REQ-MAKE-010).
DOCKER_BIN := $(shell PATH="/usr/local/bin:/usr/bin:/bin:$$PATH" command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
COMPOSE_BIN ?= docker-compose
else
COMPOSE_BIN ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
COMPOSE     ?= $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP ?= php

help:
	@echo "Wiki Bundle - Development Commands"
	@echo ""
	@echo "Container:"
	@echo "  up              Build + start the bundle container (composer + pnpm install)"
	@echo "  down            Stop the bundle container"
	@echo "  build           Rebuild the Docker image"
	@echo "  shell           Open a shell in the PHP container"
	@echo "Dependencies:"
	@echo "  install         Install Composer + pnpm dependencies"
	@echo "Assets:"
	@echo "  assets          Build TypeScript/CSS (pnpm install + pnpm run build)"
	@echo "Tests:"
	@echo "  test            Run PHPUnit tests"
	@echo "  test-coverage   Run PHPUnit with coverage (text + clover + html)"
	@echo "  test-coverage-100  Coverage + fail below 100% (REQ-TEST-003)"
	@echo "  test-ts         Vitest with coverage (fails below the vitest.config.ts thresholds)"
	@echo "Quality:"
	@echo "  cs-check        PHP-CS-Fixer dry run"
	@echo "  cs-fix          Apply PHP-CS-Fixer"
	@echo "  rector          Apply Rector"
	@echo "  rector-dry      Rector dry run"
	@echo "  phpstan         PHPStan (level 8, ignoreErrors: [])"
	@echo "  igor            Igor worker-state audit"
	@echo "  qa              cs-check + test"
	@echo "Release:"
	@echo "  release-check   Full pre-release pipeline (REQ-MAKE-002)"
	@echo "  composer-sync   composer validate --strict + install"
	@echo "Cleanup:"
	@echo "  clean           Stop containers and remove coverage artifacts"
	@echo "Composer:"
	@echo "  update          composer update in the bundle container"
	@echo "  update-deps     composer update in the bundle and every demo (monorepo helper)"
	@echo "  validate        composer validate --strict"
	@echo ""
	@echo "Bundle-specific:"
	@echo "  validate-translations  YAML syntax + 7-locale key parity (REQ-MAKE-004 / REQ-I18N-002)"
	@echo "  twig-lint       Twig CS fixer lint"
	@echo "  check-twig-extra       Twig Extra usage check (REQ-TWIG-004)"
	@echo "  check-no-raw-html-form Fail on raw <form>/<input>/<textarea>/<select> in Twig (REQ-TWIG-005)"
	@echo "  check-open-prs  REQ-REL-003: fail if unresolved open PRs"
	@echo "  check-no-cursor-coauthor  REQ-GIT-001 commit trailer check"
	@echo "  setup-hooks     Install .githooks (commit-msg)"
	@echo "  demo-smoke      REQ-TEST-011: boot demo and assert HTTP 200"
	@echo ""
	@echo "Demos:"
	@echo "  make -C demo up | down | update-bundle | release-check"
	@echo "  release-check-demos  Run release-check in every demo"

build: ensure-up
	$(COMPOSE) build

clean:
	$(COMPOSE) down -v --remove-orphans 2>/dev/null || true
	rm -rf coverage coverage-ts coverage-php.txt coverage-ts.txt .phpunit.cache

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@sleep 3
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install
	@echo "Container ready."

down:
	$(COMPOSE) down

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		$(COMPOSE) up -d; sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
		$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install; \
	fi

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install

assets: ensure-up
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install
	$(COMPOSE) exec -T $(SERVICE_PHP) pnpm run build
	$(COMPOSE) exec -T $(SERVICE_PHP) cp src/Resources/assets/src/wiki.css src/Resources/public/wiki.css

test-ts: ensure-up
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install --no-frozen-lockfile 2>/dev/null || true
	set -o pipefail; $(COMPOSE) exec -T $(SERVICE_PHP) pnpm run test:coverage | tee coverage-ts.txt
	sh .scripts/ts-coverage-percent.sh coverage-ts.txt

test: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	sh .scripts/php-coverage-percent.sh coverage-php.txt

test-coverage-100: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	set -o pipefail; $(COMPOSE) exec $(SERVICE_PHP) composer test-coverage-100 | tee coverage-php.txt
	sh .scripts/php-coverage-percent.sh coverage-php.txt

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

# REQ-MAKE-004 / REQ-I18N-002: YAML syntax + required locales + key parity against en.
validate-translations: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php scripts/check-translation-parity.php

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction --prefer-dist


check-twig-extra:
	@chmod +x .scripts/check-twig-extra.sh
	@./.scripts/check-twig-extra.sh

# Run Igor worker-state audit (REQ-CS-008)
igor: ensure-up
	$(COMPOSE) exec -T php composer igor
check-no-raw-html-form:
	@chmod +x .scripts/check-no-raw-html-form.sh
	@./.scripts/check-no-raw-html-form.sh

release-check: ensure-up check-no-cursor-coauthor check-open-prs check-twig-extra check-no-raw-html-form composer-sync cs-fix cs-check rector-dry phpstan igor validate-translations test-coverage-100 release-check-demos test-ts

release-check-demos:
	@$(MAKE) -C demo release-check

# REQ-TEST-011: boot primary demo + HTTP 200
demo-smoke:
	@$(MAKE) -C demo demo-smoke

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@bash .scripts/check-open-prs.sh

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD
setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main

twig-lint: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer twig:lint || $(COMPOSE) exec -T $(SERVICE_PHP) ./vendor/bin/twig-cs-fixer lint --config=.twig-cs-fixer.php
