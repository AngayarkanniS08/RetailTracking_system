# Enterprise Database Migration Framework — Developer & Operator Guide

This document is the complete guide for using the **Enterprise Database Migration Framework** in the RetailTracking System. It covers all CLI commands, Docker execution methods, step-by-step workflows for creating and executing new migrations, rollback policies, seeder management, and CI/CD integration.

---

## 📋 Quick Reference: Docker vs Local Execution

All commands work seamlessly both **inside Docker containers** (via `docker compose` or `make`) and **locally on your host machine** (if PHP and PostgreSQL are configured).

| Task | Makefile (Docker Recommended) | Raw CLI inside Docker | Local Host CLI |
| :--- | :--- | :--- | :--- |
| **Check status** | `make migrate-status` | `docker compose exec app-api php scripts/migrate status` | `php scripts/migrate status` |
| **Validate schema** | `make migrate-validate` | `docker compose exec app-api php scripts/migrate validate` | `php scripts/migrate validate` |
| **Preview execution** | `make migrate-dryrun` | `docker compose exec app-api php scripts/migrate dry-run` | `php scripts/migrate dry-run` |
| **Apply migrations** | `make migrate-up` | `docker compose exec app-api php scripts/migrate up` | `php scripts/migrate up` |
| **Rollback last batch** | `make migrate-rollback` | `docker compose exec app-api php scripts/migrate rollback` | `php scripts/migrate rollback` |
| **Scaffold migration** | `make migrate-create MODULE=Customer NAME=add_notes` | `docker compose exec app-api php scripts/migrate generate Customer add_notes` | `php scripts/migrate generate Customer add_notes` |
| **Run seeders** | `make seed` | `docker compose exec app-api php scripts/migrate seed` | `php scripts/migrate seed` |
| **Module seeder** | `make seed-module MODULE=Auth` | `docker compose exec app-api php scripts/migrate seed --module Auth` | `php scripts/migrate seed --module Auth` |
| **Fresh re-install** | `make migrate-fresh` | `docker compose exec app-api php scripts/migrate fresh --force` | `php scripts/migrate fresh --force` |

---

## 🛠️ Step-by-Step Workflow: How to Create and Apply a New Migration

### Step 1: Scaffold the Migration File Pair
Run the `generate` command specifying the target **Module** and a descriptive **snake_case name**.

```bash
# Docker / Makefile method:
make migrate-create MODULE=Customer NAME=add_contact_notes

# Or Direct CLI method:
php scripts/migrate generate Customer add_contact_notes
```

**Output:**
```text
  ✅ Generated migration: 20260729181500_add_contact_notes
     UP:   Database/Migrations/Customer/20260729181500_add_contact_notes.up.sql
     DOWN: Database/Migrations/Customer/20260729181500_add_contact_notes.down.sql
```

The framework automatically pre-fills standard metadata headers and 14-digit UTC timestamps.

---

### Step 2: Implement the `UP` Migration (`.up.sql`)
Open the generated `.up.sql` file and add your schema change SQL.

**Example (`Database/Migrations/Customer/20260729181500_add_contact_notes.up.sql`):**
```sql
-- ============================================================
-- Module:          Customer
-- Migration Name:  add_contact_notes
-- Author:          dhanasurya
-- Created:         2026-07-29T18:15:00Z
-- Description:     Add Contact Notes
-- Purpose:         Track customer communication notes (JIRA-402)
-- Depends On:      none
-- Risk Level:      LOW
-- Transactional:   true
-- Rollback:        Available (paired .down.sql)
-- Estimated Time:  < 100ms
-- ============================================================

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS contact_notes TEXT;
```

---

### Step 3: Implement the `DOWN` Rollback Migration (`.down.sql`)
Open the paired `.down.sql` file and implement the exact reverse operation.

**Example (`Database/Migrations/Customer/20260729181500_add_contact_notes.down.sql`):**
```sql
-- ============================================================
-- Rollback for: add_contact_notes
-- Module:       Customer
-- Created:      2026-07-29T18:15:00Z
-- ============================================================

ALTER TABLE customers
    DROP COLUMN IF EXISTS contact_notes;
```

---

### Step 4: Validate Your Changes
Run the automated validation engine to check naming, metadata headers, rollback parity, checksums, and cross-module boundaries.

```bash
make migrate-validate
# Or: php scripts/migrate validate
```

---

### Step 5: Dry-Run (Preview Execution Plan)
Preview how the migration will be executed.

```bash
php scripts/migrate dry-run
```

**Output:**
```text
  Execution Plan — 1 pending migration(s)
  ┌────────────────────────────────────────────────────────────────────────────────────┐
  │ #  │ Module     │ Migration                   │ Transact. │ Est.(ms)    │
  ├────────────────────────────────────────────────────────────────────────────────────┤
  │ 1  │ Customer   │ add_contact_notes           │ ✅ Yes    │ ~100 ms     │
  └────────────────────────────────────────────────────────────────────────────────────┘
```

---

### Step 6: Apply the Migration
Execute the pending migration against the database.

```bash
make migrate-up
# Or: php scripts/migrate up
```

**Output:**
```text
  Running 1 pending migration(s) — Batch 3
  ──────────────────────────────────────────────────────────────────────
  [18:16:00] ✅ Applied: [Customer] add_contact_notes (14ms)
  [18:16:00] ✅ Completed: 1 migration(s) applied in 14ms.
```

---

## 📖 Complete Command Reference (13 Commands)

### 1. `php scripts/migrate status`
Renders a formatted ASCII status table of all applied and pending migrations, including batch numbers, execution timestamps, and runtimes.

### 2. `php scripts/migrate pending`
Lists only unapplied (pending) migration files.

### 3. `php scripts/migrate validate`
Runs all 7 static validation rules:
- `NamingRule`: Validates UTC timestamp format (`YYYYMMDDHHMMSS_name.up.sql`).
- `DuplicateTimestampRule`: Prevents timestamp collisions.
- `RollbackRule`: Ensures every new migration has a paired `.down.sql`.
- `ChecksumRule`: Verifies SHA-256 hashes of applied files to prevent tampering.
- `DependencyRule`: Validates inter-module dependencies declared in headers.
- `ModuleBoundaryRule`: Warns on cross-module table modifications.
- `DangerousSqlRule`: Flags unguarded `DROP TABLE` or `TRUNCATE` operations.

### 4. `php scripts/migrate dry-run`
Previews pending migrations in an execution plan table.

### 5. `php scripts/migrate up`
Applies all pending migrations in UTC timestamp order wrapped in transactions and protected by PostgreSQL advisory locking (`retail_pos_migration_lock`).

### 6. `php scripts/migrate rollback`
Reverts the last batch of applied migrations in reverse chronological order.

### 7. `php scripts/migrate rollback --step <N>`
Reverts the last `N` applied migrations regardless of batch.

### 8. `php scripts/migrate rollback --batch <B>`
Reverts all migrations belonging to a specific batch number `B`.

### 9. `php scripts/migrate generate <Module> <name>`
Scaffolds a timestamped `.up.sql` + `.down.sql` migration pair. Valid modules: `Auth`, `Product`, `Customer`, `Billing`, `Vendor`, `Security`, `Settings`, `Dashboard`.

### 10. `php scripts/migrate fresh --force`
Safe, module-aware drop strategy. Drops module-owned tables only while preserving extensions, custom schemas, and functions, then re-runs all migrations. Allowed in `development` and `testing` environments only.

### 11. `php scripts/migrate seed`
Discovers and executes module seeders (`Database/Seeders/*`). Seeders use `INSERT ... ON CONFLICT DO NOTHING` (idempotent).

### 12. `php scripts/migrate seed --module <Module>`
Executes seeders belonging to a single specified module (e.g. `Auth`, `Product`).

### 13. `php scripts/migrate repair --approve`
Re-registers modified or orphaned migration file checksums in the `migration_history` audit table. Logs repair actions to the audit trail.

---

## 🔒 Safety Guards & Environment Rules

- **Advisory Locking**: Prevents concurrent migration runs during parallel deployment pipelines using PostgreSQL session lock `hashtext('retail_pos_migration_lock')`.
- **Production Guard**: Commands like `fresh` are strictly blocked when `APP_ENV=production`.
- **Backup Policy Guard**: Destructive operations in `staging` or `production` require `export MIGRATION_BACKUP_VERIFIED=true`.
- **Audit Logging**: Every migration execution is logged to `migration_history` and written in JSON format to `logs/migrations.log`.
