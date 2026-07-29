<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;

/**
 * Single source of truth for all migration_history database queries.
 *
 * The MigrationRunner and other engine classes never query migration_history
 * directly — all state access goes through this repository. This keeps the
 * runner focused on orchestration and makes the state layer testable.
 */
class MigrationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Create the migration_history table if it does not exist.
     * Idempotent — safe to call on every run.
     */
    public function bootstrap(): void
    {
        $schemaSql = __DIR__ . '/../Migrations/_schema/20260729000000_create_migration_history.up.sql';

        if (file_exists($schemaSql)) {
            $this->pdo->exec(file_get_contents($schemaSql));
        } else {
            // Fallback minimal bootstrap if schema file is missing
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS migration_history (
                    id                  BIGSERIAL       PRIMARY KEY,
                    module              VARCHAR(50)     NOT NULL,
                    migration_name      VARCHAR(255)    NOT NULL,
                    migration_path      VARCHAR(500)    NOT NULL,
                    checksum            CHAR(64)        NOT NULL DEFAULT '',
                    batch               INTEGER         NOT NULL DEFAULT 1,
                    status              VARCHAR(20)     NOT NULL DEFAULT 'pending'
                                            CHECK (status IN ('pending','running','applied','failed','rolled_back')),
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
                    UNIQUE (migration_path)
            ");
        }
        $this->backfillChecksums();
    }

    private function backfillChecksums(): void
    {
        $stmt = $this->pdo->query("SELECT migration_path FROM migration_history WHERE checksum = '' OR checksum IS NULL");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $updateStmt = $this->pdo->prepare("UPDATE migration_history SET checksum = ? WHERE migration_path = ?");

        foreach ($rows as $path) {
            $absPath = __DIR__ . '/../Migrations/' . $path;
            if (file_exists($absPath)) {
                $hash = hash('sha256', file_get_contents($absPath));
                $updateStmt->execute([$hash, $path]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Return all applied migration paths (for filtering pending list).
     *
     * @return array<string, array<string, mixed>>  keyed by migration_path
     */
    public function findApplied(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM migration_history WHERE status = 'applied' ORDER BY id ASC"
        );
        $rows = $stmt->fetchAll();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['migration_path']] = $row;
        }
        return $indexed;
    }

    /**
     * Return all migrations in a specific batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM migration_history WHERE batch = ? AND status = 'applied' ORDER BY id ASC"
        );
        $stmt->execute([$batch]);
        return $stmt->fetchAll();
    }

    /**
     * Return the most recently applied batch of migrations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findLastBatch(): array
    {
        $batch = $this->getMaxBatch();
        if ($batch === 0) {
            return [];
        }
        return $this->findByBatch($batch);
    }

    /**
     * Return the stored checksum for a migration, or null if not applied.
     */
    public function getChecksum(string $migrationPath): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT checksum FROM migration_history WHERE migration_path = ?"
        );
        $stmt->execute([$migrationPath]);
        $row = $stmt->fetch();
        return $row ? $row['checksum'] : null;
    }

    /**
     * Return the current maximum batch number (0 if no migrations applied).
     */
    public function getMaxBatch(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(MAX(batch), 0) FROM migration_history WHERE status = 'applied'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return the next batch number to use.
     */
    public function getNextBatch(): int
    {
        return $this->getMaxBatch() + 1;
    }

    // -------------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------------

    public function insertPending(
        string $module,
        string $name,
        string $path,
        string $checksum,
        int    $batch
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO migration_history
                (module, migration_name, migration_path, checksum, batch, status,
                 executed_by, application_version)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ON CONFLICT (migration_path) DO NOTHING
        ");
        $stmt->execute([
            $module,
            $name,
            $path,
            $checksum,
            $batch,
            $this->executedBy(),
            $this->appVersion(),
        ]);
    }

    public function markRunning(string $path): void
    {
        $this->pdo->prepare(
            "UPDATE migration_history SET status='running', started_at=NOW() WHERE migration_path=?"
        )->execute([$path]);
    }

    public function markApplied(string $path, string $checksum, int $elapsedMs): void
    {
        $this->pdo->prepare("
            UPDATE migration_history
            SET status='applied', finished_at=NOW(),
                execution_time_ms=?, checksum=?,
                database_version=current_setting('server_version', true)
            WHERE migration_path=?
        ")->execute([$elapsedMs, $checksum, $path]);
    }

    public function markFailed(string $path, string $errorMessage): void
    {
        $this->pdo->prepare("
            UPDATE migration_history
            SET status='failed', finished_at=NOW(), error_message=?
            WHERE migration_path=?
        ")->execute([$errorMessage, $path]);
    }

    public function markRolledBack(string $path): void
    {
        $this->pdo->prepare("
            UPDATE migration_history
            SET status='rolled_back', rolled_back_at=NOW()
            WHERE migration_path=?
        ")->execute([$path]);
    }

    public function markRepaired(string $path, string $newChecksum): void
    {
        $by = $this->executedBy();
        $this->pdo->prepare("
            UPDATE migration_history
            SET checksum=?, repaired_at=NOW(), repaired_by=?
            WHERE migration_path=?
        ")->execute([$newChecksum, $by, $path]);
    }

    // -------------------------------------------------------------------------
    // All rows (for status display)
    // -------------------------------------------------------------------------

    /**
     * Return every row ordered for display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM migration_history ORDER BY batch ASC, id ASC"
        );
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function executedBy(): string
    {
        return getenv('CI_JOB_NAME')
            ?: getenv('GITHUB_ACTOR')
            ?: get_current_user()
            ?: 'unknown';
    }

    private function appVersion(): string
    {
        $versionFile = __DIR__ . '/../../VERSION';
        return file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
    }
}
