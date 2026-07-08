# Wiki Bundle - Development
.PHONY: help up down build clean shell install test test-coverage coverage-php-percent cs-check cs-fix qa assets assets-build assets-watch assets-test test-ts ensure-up rector rector-dry phpstan release-check release-check-demos composer-sync update validate validate-translations

COMPOSE_FILE ?= docker-compose.yml
COMPOSE     ?= /usr/bin/docker compose -f $(COMPOSE_FILE)
SERVICE_PHP ?= php

help:
	@echo "Wiki Bundle - Development Commands"
	@echo ""
	@echo "  up              Start Docker container"
	@echo "  down            Stop Docker container"
	@echo "  build           Build Docker image"
	@echo "  clean           Stop containers and remove coverage artifacts"
	@echo "  install         Install Composer + pnpm dependencies"
	@echo "  assets          Build TypeScript (pnpm install + pnpm run build)"
	@echo "  test-ts         Run TypeScript (Vitest) unit tests"
	@echo "  test            Run PHPUnit tests"
	@echo "  test-coverage   Run tests with code coverage"
	@echo "  release-check   Pre-release checks"
	@echo ""
	@echo "Demos: make -C demo up"

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
	$(COMPOSE) exec -T $(SERVICE_PHP) pnpm run test:coverage | tee coverage-ts.txt
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
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage-100 | tee coverage-php.txt
	sh .scripts/php-coverage-percent.sh coverage-php.txt

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

validate-translations: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'for f in src/Resources/translations/*.yaml; do php -r "require \"vendor/autoload.php\"; Symfony\\Component\\Yaml\\Yaml::parseFile(\$$argv[1]);" "$$f" || exit 1; done'

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-install

release-check: ensure-up composer-sync cs-fix cs-check rector-dry phpstan validate-translations test-coverage-100 release-check-demos test-ts

release-check-demos:
	@$(MAKE) -C demo release-check

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
