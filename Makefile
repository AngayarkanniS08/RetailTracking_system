.PHONY: up down restart migrate seed logs logs-api logs-ui logs-db logs-cache logs-all logs-php-errors logs-php-tail status test-all cache-keys cache-inventory cache-products cache-clear

# Build and start all services in the background
up:
	docker compose up -d --build

# Stop all services
down:
	docker compose down

# Restart all services
restart:
	docker compose restart

# Run database migrations
migrate:
	docker compose exec app-api php Database/Migrate.php

# Seed test database data
seed:
	docker compose exec app-api php Database/Seed.php

# View logs (usage guide)
logs:
	@echo ""
	@echo "  📋 Available log targets:"
	@echo "    make logs-api         → Backend API (app-api)  → PHP stdout + stderr (incl. PHP errors)"
	@echo "    make logs-ui          → Frontend UI (app-ui)   → Apache access/error logs"
	@echo "    make logs-db          → PostgreSQL (db)         → DB startup & query errors"
	@echo "    make logs-cache       → Valkey (valkey)         → Cache hits, misses, commands"
	@echo "    make logs-all         → All services combined   → Noisy full stream"
	@echo ""
	@echo "  🐘 PHP error_log targets (php.ini → /dev/stderr → captured by docker):"
	@echo "    make logs-php-errors  → Show past PHP Fatal/Warning/Notice lines (no follow)"
	@echo "    make logs-php-tail    → Live-follow ONLY PHP error lines (filters noise)"
	@echo ""

# View Backend API logs — PHP errors go to /dev/stderr, captured here
logs-api:
	docker compose logs -f app-api

# View Frontend UI / Apache logs
logs-ui:
	docker compose logs -f app-ui

# View PostgreSQL database logs
logs-db:
	docker compose logs -f db

# View Valkey cache logs
logs-cache:
	docker compose logs -f valkey

# View ALL service logs combined (noisy — use specific targets above)
logs-all:
	docker compose logs -f

# Dump all past PHP error lines (Fatal, Warning, Notice, Deprecated, Parse)
# php.ini sets error_log=/dev/stderr so Docker captures them in app-api logs
logs-php-errors:
	docker compose logs app-api 2>&1 | grep -E "PHP (Fatal|Warning|Notice|Deprecated|Parse|Uncaught|Error)" || echo "No PHP errors found in log history."

# Live-follow ONLY PHP error lines (strips Apache request noise)
logs-php-tail:
	docker compose logs -f app-api 2>&1 | grep --line-buffered -E "PHP (Fatal|Warning|Notice|Deprecated|Parse|Uncaught|Error)"

# View container status
status:
	docker compose ps

# Helper to verify all makefile commands (runs in sequence)
test-all:
	@echo "=== Testing down ==="
	docker compose down
	@echo "=== Testing up ==="
	docker compose up -d --build
	@echo "=== Testing status ==="
	docker compose ps
	@echo "=== Testing migrate ==="
	docker compose exec app-api php Database/Migrate.php
	@echo "=== Testing seed ==="
	docker compose exec app-api php Database/Seed.php
	@echo "=== Testing restart ==="
	docker compose restart

# List all keys in Valkey cache
cache-keys:
	docker exec -it retail_valkey valkey-cli keys "*"

# List only inventory search cache keys
cache-inventory:
	docker exec -it retail_valkey valkey-cli keys "inventory:batches:*"

# List only product search cache keys
cache-products:
	docker exec -it retail_valkey valkey-cli keys "products:search:*"

cache-vendor:
	docker exec -it retail_valkey valkey-cli keys "vendors:list:*"

cache-clear:
	docker exec -it retail_valkey valkey-cli flushall

