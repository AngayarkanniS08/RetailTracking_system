<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;

/**
 * PostgreSQL advisory lock to prevent concurrent migration runs.
 *
 * Uses session-scoped pg_try_advisory_lock so the lock is automatically
 * released if the process crashes — no orphaned lock rows.
 *
 * Lock key is a stable integer derived from the application name so
 * different applications on the same DB server don't block each other.
 */
class MigrationLock
{
    private const LOCK_KEY   = 'retail_pos_migration_lock';
    private const TIMEOUT_S  = 30;

    private PDO $pdo;
    private bool $held = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Acquire the advisory lock. Retries for up to TIMEOUT_S seconds.
     *
     * @throws \RuntimeException if the lock cannot be acquired in time
     */
    public function acquire(): void
    {
        $start = time();

        while (true) {
            $stmt = $this->pdo->query(
                "SELECT pg_try_advisory_lock(hashtext(" . $this->pdo->quote(self::LOCK_KEY) . "))"
            );

            $acquired = (bool) $stmt->fetchColumn();

            if ($acquired) {
                $this->held = true;
                MigrationLogger::debug('Advisory lock acquired', ['key' => self::LOCK_KEY]);
                return;
            }

            if ((time() - $start) >= self::TIMEOUT_S) {
                throw new \RuntimeException(
                    "Could not acquire migration lock after " . self::TIMEOUT_S . "s.\n" .
                    "Another deployment pipeline may be running migrations.\n" .
                    "Wait for it to complete or check pg_locks if this is unexpected."
                );
            }

            MigrationLogger::warn('Waiting for migration lock...', ['elapsed_s' => time() - $start]);
            sleep(2);
        }
    }

    /**
     * Release the advisory lock. Safe to call even if not held.
     */
    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $this->pdo->query(
            "SELECT pg_advisory_unlock(hashtext(" . $this->pdo->quote(self::LOCK_KEY) . "))"
        );

        $this->held = false;
        MigrationLogger::debug('Advisory lock released', ['key' => self::LOCK_KEY]);
    }

    /**
     * Whether this instance currently holds the lock.
     */
    public function isHeld(): bool
    {
        return $this->held;
    }
}
