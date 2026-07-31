# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell PATH="/usr/local/bin:/usr/bin:/bin:$$PATH" command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
COMPOSE ?= docker-compose
else
COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP = php

.PHONY: help ensure-env ensure-up up down build install database shell test update-bundle fixtures seed restart verify release-check

help:
	@echo "Wiki Bundle — Symfony 8.1 Demo"
	@echo ""
	@echo "  up             Start demo (FrankenPHP, port from .env)"
	@echo "  down           Stop containers"
	@echo "  restart        Restart PHP service"
	@echo "  verify         HTTP 200 check on /tools/wiki"
	@echo "  install        Install Composer dependencies"
	@echo "  database       Create DB and run migrations"
	@echo "  fixtures       Load demo fixtures (append)"
	@echo "  seed           Reload demo wiki content"
	@echo "  shell          Open shell in PHP container"
	@echo "  test           Run demo smoke checks"
	@echo "  update-bundle  Sync bundles, assets:install, clear cache"
	@echo "  release-check  update-bundle + test + verify"

ensure-env:
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")
	@rm -f .env.dev

ensure-up: ensure-env
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting demo container..."; \
		$(MAKE) up; \
	fi

up: ensure-env
	@$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction --no-scripts
	@$(COMPOSE) exec -T $(SERVICE_PHP) rm -rf var/cache/* 2>/dev/null || true
	@$(COMPOSE) exec -T $(SERVICE_PHP) test -f bin/console && $(COMPOSE) exec -T $(SERVICE_PHP) composer run-script post-install-cmd || true
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:database:create --if-not-exists
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:migrations:migrate --no-interaction
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink --relative
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:fixtures:load --no-interaction --append || true
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT/tools/wiki"

down:
	@$(COMPOSE) down

restart: ensure-env
	@$(COMPOSE) restart $(SERVICE_PHP)

verify: ensure-up
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8025; \
	code=$$(curl -sL -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/tools/wiki" || true); \
	if [ "$$code" != "200" ]; then echo "verify failed HTTP $$code"; exit 1; fi; \
	echo "verify OK (HTTP $$code)"

build:
	@$(COMPOSE) build

install:
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer install

database:
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:database:create --if-not-exists
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:fixtures:load --no-interaction --append

seed: fixtures

shell:
	@$(COMPOSE) exec $(SERVICE_PHP) sh

update-bundle: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update nowo-tech/wiki-bundle --no-interaction --no-scripts
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer dump-autoload
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink --relative
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --env=dev --no-warmup || true

update-deps: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

test: update-bundle
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:yaml config/ --parse-tags
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:twig templates/
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:schema:validate --skip-sync

release-check: ensure-up update-bundle test verify
	@echo "✅ Demo passed release-check."
