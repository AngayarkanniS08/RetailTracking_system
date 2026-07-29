# Database Directory & Migration Infrastructure

This directory contains the database migration engine, module SQL migrations, and seeder classes for the RetailTracking System.

For complete documentation, CLI command usage, Docker instructions, and step-by-step migration creation workflows, please refer to:
👉 **[docs/MIGRATIONS.md](../docs/MIGRATIONS.md)**

---

## Directory Structure

```
Database/
├── Engine/              ← Core OOP Migration Engine (Runner, Planner, Validator, Lock, etc.)
│   └── Rules/           ← Composable validation rule objects
├── Migrations/          ← Module schema migrations
│   ├── Auth/            ← Auth module migrations
│   ├── Product/         ← Product module migrations
│   ├── Customer/        ← Customer module migrations
│   ├── Billing/         ← Billing module migrations
│   ├── Vendor/          ← Vendor module migrations
│   ├── Security/        ← Security hardening & RLS migrations
│   ├── Settings/        ← Backup & system configuration migrations
│   ├── Dashboard/       ← Reporting views & aggregations
│   ├── _schema/         ← System audit table schema
│   └── _templates/      ← Generator templates (.up.sql & .down.sql)
├── Seeders/             ← Idempotent module seeders
│   ├── Auth/            ← Auth test data seeders
│   └── Product/         ← Product catalog seeders
├── Migrate.php          ← Backward-compatible shim for Docker CMD
└── Seed.php             ← Backward-compatible shim for make seed
```

---

## Quick Command Summary

```bash
# Check status
make migrate-status

# Validate migration files
make migrate-validate

# Scaffold a new migration pair
make migrate-create MODULE=Customer NAME=add_contact_notes

# Apply pending migrations
make migrate-up

# Roll back last batch
make migrate-rollback

# Run seeders
make seed
```
