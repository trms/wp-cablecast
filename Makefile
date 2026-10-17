-include .env

# Local development helpers. See "Local Development" in README.md.

PHP ?= $(or $(PHP_VERSION),8.2)
WP  ?= $(or $(WP_VERSION),latest)

COMPOSE = docker compose
WPCLI   = $(COMPOSE) run --rm cli

.PHONY: up down setup wp sync logs debug-log tools test test-matrix test-clean reset

up: ## Start the dev site and install WordPress on first run
	$(COMPOSE) up -d --build
	$(WPCLI) sh wp-content/plugins/wp-cablecast/bin/dev-setup.sh

down: ## Stop containers (keeps data)
	$(COMPOSE) --profile tools down

setup: ## Re-run WordPress install / plugin activation
	$(WPCLI) sh wp-content/plugins/wp-cablecast/bin/dev-setup.sh

wp: ## Run WP-CLI: make wp ARGS="plugin list"
	$(WPCLI) wp $(ARGS)

sync: ## Run the Cablecast sync now instead of waiting for cron
	$(WPCLI) wp cron event run cablecast_cron_hook

logs: ## Tail container logs
	$(COMPOSE) logs -f

debug-log: ## Tail wp-content/debug.log
	$(COMPOSE) exec wordpress tail -f wp-content/debug.log

tools: ## Start phpMyAdmin on http://localhost:8081
	$(COMPOSE) --profile tools up -d phpmyadmin

test: ## Run PHPUnit: make test [PHP=7.4] [WP=6.2] [ARGS="--filter Foo"]
	PHP_VERSION=$(PHP) WP_VERSION=$(WP) $(COMPOSE) run --rm --build tests bash bin/docker-test.sh $(ARGS)

test-matrix: ## Run the same PHP x WP matrix as CI
	@set -e; for php in 7.4 8.0 8.1 8.2; do for wp in 6.2 6.4 latest; do \
		$(MAKE) --no-print-directory test PHP=$$php WP=$$wp; \
	done; done

test-clean: ## Drop cached WP test suites (e.g. to pick up a new "latest")
	$(COMPOSE) down
	docker volume rm $$(basename $(CURDIR))_test-cache

reset: ## Destroy everything, including the database
	$(COMPOSE) --profile tools --profile cli --profile tests down -v
