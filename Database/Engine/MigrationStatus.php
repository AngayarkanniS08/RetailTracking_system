<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Renders the migration status table and dry-run execution plans.
 *
 * Status table columns:
 *   #  | Module  | Migration               | Status      | Batch | Executed At       | Runtime
 *
 * Dry-run plan columns:
 *   #  | Module  | Migration               | Transact.  | Est.(ms)
 */
class MigrationStatus
{
    // -------------------------------------------------------------------------
    // Status Table
    // -------------------------------------------------------------------------

    /**
     * Print a formatted status table for all known migrations.
     *
     * @param array<int, array<string, mixed>> $allRows  From MigrationRepository::findAll()
     */
    public function printStatusTable(array $allRows): void
    {
        MigrationLogger::heading('Migration Status');

        if (empty($allRows)) {
            echo "  No migrations found in history.\n\n";
            return;
        }

        $rows = [];
        foreach ($allRows as $i => $row) {
            $status    = $row['status'] ?? 'unknown';
            $statusStr = $this->formatStatus($status);
            $execAt    = $row['finished_at'] ? substr($row['finished_at'], 0, 19) : '—';
            $runtime   = isset($row['execution_time_ms']) ? $row['execution_time_ms'] . ' ms' : '—';
            $name      = $row['migration_name'] ?? basename($row['migration_path']);
            $name      = strlen($name) > 38 ? substr($name, 0, 35) . '...' : $name;

            $rows[] = [
                '#'     => $i + 1,
                'Mod'   => str_pad($row['module'] ?? '—', 10),
                'Name'  => str_pad($name, 38),
                'Status'=> str_pad($statusStr, 14),
                'Batch' => str_pad((string)($row['batch'] ?? '—'), 5),
                'ExecAt'=> str_pad($execAt, 19),
                'Time'  => $runtime,
            ];
        }

        $this->printTable(
            ['#', 'Module', 'Migration', 'Status', 'Batch', 'Executed At', 'Runtime'],
            $rows
        );
    }

    // -------------------------------------------------------------------------
    // Dry-run Plan
    // -------------------------------------------------------------------------

    /**
     * Print a rich execution plan for pending migrations.
     *
     * @param array<int, array<string, mixed>> $pending  Pending plan items
     */
    public function printDryRunPlan(array $pending): void
    {
        MigrationLogger::heading("Execution Plan — " . count($pending) . " pending migration(s)");

        if (empty($pending)) {
            echo "  ✅ No pending migrations. Database is up-to-date.\n\n";
            return;
        }

        $totalMs = 0;
        $rows    = [];

        foreach ($pending as $i => $plan) {
            $name   = $plan['migration_name'] ?? basename($plan['relative_path']);
            $name   = strlen($name) > 40 ? substr($name, 0, 37) . '...' : $name;
            $trans  = ($plan['is_transactional'] ?? true) ? '✅ Yes' : '❌ No ';
            $est    = isset($plan['estimated_ms']) ? "~{$plan['estimated_ms']} ms" : 'N/A';
            $totalMs += $plan['estimated_ms'] ?? 0;

            $rows[] = [
                '#'     => $i + 1,
                'Mod'   => str_pad($plan['module'] ?? '—', 10),
                'Name'  => str_pad($name, 40),
                'Trans' => str_pad($trans, 9),
                'Est'   => str_pad($est, 8),
            ];
        }

        $this->printTable(
            ['#', 'Module', 'Migration', 'Transact.', 'Est.(ms)'],
            $rows
        );

        $totalStr = $totalMs > 0 ? "~{$totalMs} ms" : 'N/A';
        echo str_repeat(' ', 52) . "Estimated Total:  {$totalStr}\n\n";
        echo "  [DRY-RUN] No changes applied. Run `php scripts/migrate up` to execute.\n\n";
    }

    // -------------------------------------------------------------------------
    // Pending list
    // -------------------------------------------------------------------------

    /** @param array<int, array<string, mixed>> $pending */
    public function printPendingList(array $pending): void
    {
        MigrationLogger::heading('Pending Migrations');

        if (empty($pending)) {
            echo "  ✅ No pending migrations.\n\n";
            return;
        }

        foreach ($pending as $i => $plan) {
            $n    = $i + 1;
            $mod  = $plan['module'] ?? '—';
            $name = $plan['migration_name'] ?? basename($plan['relative_path']);
            echo "  {$n}. [{$mod}] {$name}\n";
        }
        echo "\n";
    }

    // -------------------------------------------------------------------------
    // Generic table renderer
    // -------------------------------------------------------------------------

    /** @param array<int, array<string, mixed>> $rows */
    private function printTable(array $headers, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Build header row
        $header = implode(' │ ', array_map(fn($h) => str_pad($h, 12), $headers));
        $divider = str_repeat('─', strlen($header) + 4);

        echo "  ┌{$divider}┐\n";
        echo "  │ {$header} │\n";
        echo "  ├{$divider}┤\n";

        foreach ($rows as $row) {
            $line = implode(' │ ', array_values($row));
            echo "  │ {$line} │\n";
        }

        echo "  └{$divider}┘\n\n";
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'applied'      => "\033[32mapplied\033[0m",
            'pending'      => "\033[33mpending\033[0m",
            'failed'       => "\033[31mfailed\033[0m",
            'running'      => "\033[36mrunning\033[0m",
            'rolled_back'  => "\033[35mrolled_back\033[0m",
            default        => $status,
        };
    }
}
