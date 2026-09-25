# Shortcuts for the Docker dev environment.
DC = docker compose
ART = $(DC) exec app php artisan

up:            ## Start all containers
	$(DC) up -d --build

down:          ## Stop containers (data volumes are kept)
	$(DC) down

setup: up      ## First run: install deps, key, central DB + seed
	$(DC) exec app sh -c '[ -f .env ] || cp .env.example .env'
	$(DC) exec app composer install
	$(ART) key:generate
	$(ART) migrate --seed

tenant:        ## Create a dev tenant: make tenant slug=tokoabc name="Toko ABC"
	$(ART) erp:tenant:create $(slug) --name="$(or $(name),$(slug))"

tenant-delete: ## Drop a dev tenant: make tenant-delete slug=tokoabc
	$(ART) erp:tenant:delete $(slug) --force

test:          ## Run the test suite (uses erp_central_test)
	$(ART) test

shell:         ## Shell in the app container
	$(DC) exec app bash

.PHONY: up down setup tenant tenant-delete test shell
