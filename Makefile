.PHONY: up down restart migrate seed logs status test-all cache-keys cache-inventory cache-products cache-clear

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

# View application logs
logs:
	docker compose logs -f

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

# Clear all Valkey cache
cache-clear:
	docker exec -it retail_valkey valkey-cli flushall
