<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;

/**
 * Executes a single migration file.
 *
 * Single responsibility: run one SQL file, wrap in a transaction if declared,
 * measure elapsed time, and report timing. The executor does NOT touch
 * migration_history — that is the Repository's job.
 */
class MigrationExecutor
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute one migration plan item.
     *
     * @param  array<string, mixed> $plan  A plan item from MigrationPlanner
     * @return int                         Execution time in milliseconds
     * @throws \RuntimeException           On SQL failure
     */
    public function execute(array $plan): int
    {
        $absolutePath    = $plan['absolute_path'];
        $isTransactional = $plan['is_transactional'] ?? true;

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("Migration file not found: {$absolutePath}");
        }

        $sql = trim(file_get_contents($absolutePath));

        if (empty($sql)) {
            MigrationLogger::warn("Skipping empty migration file: {$plan['relative_path']}");
            return 0;
        }

        $start = microtime(true);

        if ($isTransactional) {
            $this->executeInTransaction($sql, $absolutePath);
        } else {
            $this->executeRaw($sql, $absolutePath);
        }

        return (int) round((microtime(true) - $start) * 1000);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function executeInTransaction(string $sql, string $path): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec($sql);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException(
                "Migration failed (rolled back): {$path}\n" .
                "SQL Error: " . $e->getMessage()
            );
        }
    }

    private function executeRaw(string $sql, string $path): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Migration failed (non-transactional, no rollback): {$path}\n" .
                "SQL Error: " . $e->getMessage()
            );
        }
    }
}
