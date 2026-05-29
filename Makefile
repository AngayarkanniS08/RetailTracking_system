.PHONY: up down restart migrate seed logs status test-all

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
	docker compose exec web php Database/Migrate.php

# Seed test database data
seed:
	docker compose exec web php Database/Seed.php

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
	docker compose exec web php Database/Migrate.php
	@echo "=== Testing seed ==="
	docker compose exec web php Database/Seed.php
	@echo "=== Testing restart ==="
	docker compose restart
