-- ============================================================
-- Module:          _schema (infrastructure)
-- Migration Name:  create_migration_history
-- Author:          system
-- Created:         2026-07-29T00:00:00Z
-- Description:     Creates the migration_history audit table
-- Purpose:         Replaces the legacy migrations table with a
--                  full audit table supporting batch tracking,
--                  checksums, rollback state, and repair history.
-- Depends On:      none
-- Risk Level:      LOW
-- Transactional:   true
-- Rollback:        Available (paired .down.sql)
-- Estimated Time:  < 50ms
-- ============================================================

-- Drop legacy tracking table if it exists (migrate from old schema)
-- The legacy `migrations` table only stored filename + executed_at.
-- We preserve existing records by migrating them below.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'migrations'
    ) THEN
        DROP TABLE IF EXISTS legacy_migrations_backup;
        CREATE TEMP TABLE legacy_migrations_backup AS
            SELECT filename, executed_at FROM migrations;
    END IF;
END $$;

-- Create the new audit table
CREATE TABLE IF NOT EXISTS migration_history (
    id                  BIGSERIAL       PRIMARY KEY,
    module              VARCHAR(50)     NOT NULL,
    migration_name      VARCHAR(255)    NOT NULL,
    migration_path      VARCHAR(500)    NOT NULL,
    checksum            CHAR(64)        NOT NULL DEFAULT '',
    batch               INTEGER         NOT NULL DEFAULT 1,
    status              VARCHAR(20)     NOT NULL DEFAULT 'pending'
                            CHECK (status IN (
                                'pending', 'running', 'applied', 'failed', 'rolled_back'
                            )),
    executed_by         VARCHAR(100),
    started_at          TIMESTAMPTZ,
    finished_at         TIMESTAMPTZ,
    execution_time_ms   INTEGER,
    rolled_back_at      TIMESTAMPTZ,
    application_version VARCHAR(50),
    database_version    VARCHAR(50),
    error_message       TEXT,
    repaired_at         TIMESTAMPTZ,
    repaired_by         VARCHAR(100),
    CONSTRAINT uq_migration_history_path UNIQUE (migration_path)
);

-- Indexes for common query patterns
CREATE INDEX IF NOT EXISTS idx_migration_history_status
    ON migration_history (status);

CREATE INDEX IF NOT EXISTS idx_migration_history_batch
    ON migration_history (batch);

CREATE INDEX IF NOT EXISTS idx_migration_history_module
    ON migration_history (module);

-- Migrate legacy records into the new table
INSERT INTO migration_history (module, migration_name, migration_path, batch, status, finished_at)
SELECT
    COALESCE(
        CASE
            WHEN filename LIKE 'Auth/%'      THEN 'Auth'
            WHEN filename LIKE 'Product/%'   THEN 'Product'
            WHEN filename LIKE 'Products/%'  THEN 'Product'
            WHEN filename LIKE 'Customer/%'  THEN 'Customer'
            WHEN filename LIKE 'Billing/%'   THEN 'Billing'
            WHEN filename LIKE 'Vendor/%'    THEN 'Vendor'
            WHEN filename LIKE 'Security/%'  THEN 'Security'
            WHEN filename LIKE 'Settings/%'  THEN 'Settings'
            WHEN filename LIKE 'Dashboard/%' THEN 'Dashboard'
            ELSE 'Unknown'
        END,
        'Unknown'
    )                       AS module,
    REGEXP_REPLACE(
        REGEXP_REPLACE(filename, '^[^/]+/', ''),
        '\.sql$', ''
    )                       AS migration_name,
    filename                AS migration_path,
    1                       AS batch,
    'applied'               AS status,
    executed_at             AS finished_at
FROM migrations
ON CONFLICT (migration_path) DO NOTHING;
