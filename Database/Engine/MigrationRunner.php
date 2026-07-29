<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;

/**
 * Main orchestrator for all migration commands.
 *
 * MigrationRunner is a thin coordinator — it delegates every concern
 * to a specialist component. It owns no business logic itself.
 *
 * Command flow:
 *   CLI → MigrationRunner
 *              │
 *         MigrationLock     (concurrency safety)
 *              │
 *         MigrationPlanner  (discover + sort)
 *              │
 *         MigrationValidator (rule pipeline)
 *              │
 *         MigrationExecutor (per-file execution)
 *              │
 *         MigrationRepository (history state)
 *              │
 *         EventDispatcher   (lifecycle hooks)
 */
class MigrationRunner
{
    private PDO                 $pdo;
    private MigrationRepository $repository;
    private MigrationPlanner    $planner;
    private MigrationValidator  $validator;
    private MigrationExecutor   $executor;
    private MigrationStatus     $status;
    private MigrationLock       $lock;
    private EventDispatcher     $events;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->repository = new MigrationRepository($pdo);
        $this->planner    = new MigrationPlanner();
        $this->validator  = new MigrationValidator();
        $this->executor   = new MigrationExecutor($pdo);
        $this->status     = new MigrationStatus();
        $this->lock       = new MigrationLock($pdo);
        $this->events     = new EventDispatcher();
    }

    // =========================================================================
    // UP — Run pending migrations
    // =========================================================================

    public function up(): void
    {
        $this->bootstrap();
        $this->lock->acquire();

        try {
            $appliedIndex = $this->repository->findApplied();
            $allPlans     = $this->planner->plan($appliedIndex);

            $this->validator->validate($allPlans);

            $pending = array_filter($allPlans, fn($p) => $p['status'] === 'pending');
            $pending = array_values($pending);

            if (empty($pending)) {
                MigrationLogger::info('No pending migrations. Database is up-to-date.');
                return;
            }

            $batch     = $this->repository->getNextBatch();
            $runStart  = microtime(true);
            $count     = 0;

            $this->events->dispatch('before.run', ['pending_count' => count($pending)]);

            MigrationLogger::heading(sprintf(
                'Running %d pending migration(s) — Batch %d',
                count($pending),
                $batch
            ));

            foreach ($pending as $plan) {
                $this->runOneMigration($plan, $batch);
                $count++;
            }

            $elapsed = round((microtime(true) - $runStart) * 1000);
            $this->events->dispatch('after.run', ['count' => $count, 'elapsed_ms' => $elapsed]);

            MigrationLogger::info(
                "Completed: {$count} migration(s) applied in {$elapsed}ms.",
                ['batch' => $batch]
            );

        } finally {
            $this->lock->release();
        }
    }

    // =========================================================================
    // ROLLBACK
    // =========================================================================

    /**
     * @param int|null    $step   Roll back N individual migrations
     * @param int|null    $batch  Roll back a specific batch number
     */
    public function rollback(?int $step = null, ?int $batch = null): void
    {
        BackupGuard::check('rollback');
        $this->bootstrap();
        $this->lock->acquire();

        try {
            // Resolve target set
            if ($batch !== null) {
                $targets = $this->repository->findByBatch($batch);
            } elseif ($step !== null) {
                $allApplied = $this->repository->findApplied();
                $targets    = array_slice(array_reverse(array_values($allApplied)), 0, $step);
            } else {
                $targets = $this->repository->findLastBatch();
            }

            if (empty($targets)) {
                MigrationLogger::info('Nothing to roll back.');
                return;
            }

            // Reverse chronological order for rollback
            usort($targets, fn($a, $b) => strcmp($b['migration_path'], $a['migration_path']));

            $this->events->dispatch('before.rollback', ['count' => count($targets)]);
            MigrationLogger::heading('Rolling back ' . count($targets) . ' migration(s)...');

            foreach ($targets as $row) {
                $this->rollbackOne($row);
            }

            $this->events->dispatch('after.rollback', ['count' => count($targets)]);
            MigrationLogger::info('Rollback complete.');

        } finally {
            $this->lock->release();
        }
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    public function status(): void
    {
        $this->bootstrap();
        $allRows = $this->repository->findAll();
        $this->status->printStatusTable($allRows);

        // Exit code 1 if any pending migrations exist (useful in CI)
        $appliedIndex = $this->repository->findApplied();
        $allPlans     = $this->planner->plan($appliedIndex);
        $pending      = array_filter($allPlans, fn($p) => $p['status'] === 'pending');

        if (!empty($pending)) {
            exit(1);
        }
    }

    // =========================================================================
    // PENDING
    // =========================================================================

    public function pending(): void
    {
        $this->bootstrap();
        $appliedIndex = $this->repository->findApplied();
        $allPlans     = $this->planner->plan($appliedIndex);
        $pending      = array_values(array_filter($allPlans, fn($p) => $p['status'] === 'pending'));
        $this->status->printPendingList($pending);
    }

    // =========================================================================
    // VALIDATE
    // =========================================================================

    public function validate(): void
    {
        $this->bootstrap();
        $appliedIndex = $this->repository->findApplied();
        $allPlans     = $this->planner->plan($appliedIndex);
        $this->validator->validate($allPlans);
        MigrationLogger::info('Validation passed. No issues found.');
    }

    // =========================================================================
    // DRY-RUN
    // =========================================================================

    public function dryRun(): void
    {
        $this->bootstrap();
        $appliedIndex = $this->repository->findApplied();
        $allPlans     = $this->planner->plan($appliedIndex);
        $this->validator->validate($allPlans);
        $pending = array_values(array_filter($allPlans, fn($p) => $p['status'] === 'pending'));
        $this->status->printDryRunPlan($pending);
    }

    // =========================================================================
    // FRESH
    // =========================================================================

    public function fresh(bool $force = false): void
    {
        if (!$force) {
            throw new \RuntimeException(
                "fresh requires --force flag.\nUsage: php scripts/migrate fresh --force"
            );
        }

        $freshRunner = new FreshRunner($this->pdo);
        $freshRunner->run();

        // Re-bootstrap history table after drop
        $this->repository->bootstrap();

        MigrationLogger::info('Running all migrations from scratch...');
        $this->up();
    }

    // =========================================================================
    // GENERATE
    // =========================================================================

    public function generate(string $module, string $name): void
    {
        $generator = new MigrationGenerator();
        $files     = $generator->generate($module, $name);

        echo "\n  ✅ Migration generated:\n";
        echo "     UP:   {$files['up']}\n";
        echo "     DOWN: {$files['down']}\n\n";
        echo "  Next steps:\n";
        echo "    1. Implement your schema change in the .up.sql file\n";
        echo "    2. Implement the exact reverse in the .down.sql file\n";
        echo "    3. Run: php scripts/migrate validate\n";
        echo "    4. Run: php scripts/migrate dry-run\n";
        echo "    5. Run: php scripts/migrate up\n\n";
    }

    // =========================================================================
    // REPAIR
    // =========================================================================

    public function repair(bool $approve = false): void
    {
        if (!$approve) {
            throw new \RuntimeException(
                "repair requires --approve flag to confirm the action.\n" .
                "Usage: php scripts/migrate repair --approve\n\n" .
                "This command re-registers orphaned migration files and updates checksums.\n" .
                "The action is logged to migration_history for audit purposes."
            );
        }

        BackupGuard::check('repair');
        $this->bootstrap();

        $appliedIndex = $this->repository->findApplied();
        $allPlans     = $this->planner->plan($appliedIndex);
        $repaired     = 0;

        foreach ($allPlans as $plan) {
            if ($plan['status'] !== 'applied') {
                continue;
            }

            $stored  = $plan['stored_checksum'] ?? '';
            $current = MigrationChecksum::compute($plan['absolute_path']);

            if ($stored !== $current) {
                $this->repository->markRepaired($plan['relative_path'], $current);
                $this->events->dispatch('migration.repaired', [
                    'file'           => $plan['relative_path'],
                    'old_checksum'   => $stored,
                    'new_checksum'   => $current,
                ]);
                MigrationLogger::warn(
                    "Repaired checksum for: {$plan['relative_path']}",
                    ['old' => $stored, 'new' => $current]
                );
                $repaired++;
            }
        }

        if ($repaired === 0) {
            MigrationLogger::info('No orphaned migrations found. Everything is consistent.');
        } else {
            MigrationLogger::info("Repaired {$repaired} migration(s). Audit trail updated.");
        }
    }

    // =========================================================================
    // Internal
    // =========================================================================

    private function bootstrap(): void
    {
        $this->repository->bootstrap();
    }

    private function runOneMigration(array $plan, int $batch): void
    {
        $path     = $plan['relative_path'];
        $name     = $plan['migration_name'];
        $checksum = MigrationChecksum::compute($plan['absolute_path']);

        // Register as pending with batch number
        $this->repository->insertPending(
            $plan['module'], $name, $path, $checksum, $batch
        );
        $this->repository->markRunning($path);

        $this->events->dispatch('before.migration', ['plan' => $plan, 'batch' => $batch]);

        try {
            $elapsedMs = $this->executor->execute($plan);
            $this->repository->markApplied($path, $checksum, $elapsedMs);

            $this->events->dispatch('after.migration', [
                'plan'       => $plan,
                'elapsed_ms' => $elapsedMs,
                'batch'      => $batch,
            ]);

            MigrationLogger::info(
                "Applied: [{$plan['module']}] {$name} ({$elapsedMs}ms)",
                ['event' => 'migration.applied', 'module' => $plan['module'], 'batch' => $batch]
            );

        } catch (\Throwable $e) {
            $this->repository->markFailed($path, $e->getMessage());
            $this->events->dispatch('migration.failed', ['plan' => $plan, 'error' => $e->getMessage()]);
            MigrationLogger::error("Failed: {$name}\n  " . $e->getMessage());
            throw new \RuntimeException("Migration aborted: {$name}", 0, $e);
        }
    }

    private function rollbackOne(array $row): void
    {
        $migrationPath = $row['migration_path'];
        $absoluteUp    = __DIR__ . '/../Migrations/' . $migrationPath;
        $absoluteDown  = str_replace('.sql', '.down.sql', str_replace('.up.sql', '', $absoluteUp));

        // Handle legacy files without .up.sql suffix
        if (!str_ends_with($absoluteUp, '.up.sql')) {
            $absoluteDown = $absoluteUp . '.down.sql'; // fallback guess
        }

        if (!file_exists($absoluteDown)) {
            MigrationLogger::warn(
                "No rollback file found — skipping: {$migrationPath}",
                ['reason' => 'Legacy migration or missing down.sql']
            );
            return;
        }

        $this->events->dispatch('before.rollback', ['path' => $migrationPath]);

        $plan = [
            'absolute_path'   => $absoluteDown,
            'relative_path'   => $migrationPath,
            'is_transactional'=> true,
            'migration_name'  => basename($migrationPath),
        ];

        try {
            $this->executor->execute($plan);
            $this->repository->markRolledBack($migrationPath);
            $this->events->dispatch('after.rollback', ['path' => $migrationPath]);
            MigrationLogger::info("Rolled back: {$migrationPath}");
        } catch (\Throwable $e) {
            MigrationLogger::error("Rollback failed: {$migrationPath}\n  " . $e->getMessage());
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Accessors for SeederRunner integration
    // -------------------------------------------------------------------------

    public function getEventDispatcher(): EventDispatcher
    {
        return $this->events;
    }
}
