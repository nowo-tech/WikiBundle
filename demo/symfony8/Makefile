COMPOSE = /usr/bin/docker compose
SERVICE_PHP = php

.PHONY: help ensure-env up down build install database shell test update-bundle fixtures seed

help:
	@echo "Wiki Bundle — Symfony 8.1 Demo"
	@echo ""
	@echo "  up             Start demo (FrankenPHP, port from .env)"
	@echo "  down           Stop containers"
	@echo "  install        Install Composer dependencies"
	@echo "  database       Create DB and run migrations"
	@echo "  fixtures       Load demo fixtures (append)"
	@echo "  seed           Reload demo wiki content"
	@echo "  shell          Open shell in PHP container"
	@echo "  test           Run demo smoke checks"
	@echo "  update-bundle  Sync bundles, assets:install, clear cache"

ensure-env:
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")
	@rm -f .env.dev

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

update-bundle:
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update nowo-tech/wiki-bundle --no-interaction --no-scripts
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer dump-autoload
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink --relative
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --env=dev --no-warmup || true

update-deps:
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

test: update-bundle
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:yaml config/ --parse-tags
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:twig templates/
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:schema:validate --skip-sync
