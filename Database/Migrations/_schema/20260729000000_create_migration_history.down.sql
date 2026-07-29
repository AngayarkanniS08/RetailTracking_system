-- ============================================================
-- Rollback: create_migration_history
-- ============================================================
-- WARNING: Drops the migration_history audit table.
-- This should only ever be run in development environments.
-- ============================================================

DROP TABLE IF EXISTS migration_history CASCADE;

DROP INDEX IF EXISTS idx_migration_history_status;
DROP INDEX IF EXISTS idx_migration_history_batch;
DROP INDEX IF EXISTS idx_migration_history_module;
