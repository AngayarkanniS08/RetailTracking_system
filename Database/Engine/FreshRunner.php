<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;

/**
 * Safe module-aware drop strategy for the `fresh` command.
 *
 * NEVER uses DROP SCHEMA public CASCADE — that would destroy extensions,
 * functions, custom schemas, and permissions.
 *
 * Instead:
 *   1. Discover all tables currently in the `public` schema
 *   2. Cross-reference with the module ownership registry
 *   3. Drop only module-owned tables in dependency-safe order
 *   4. Preserve: migration_history, extensions, functions, non-owned tables
 *
 * Only available when APP_ENV !== 'production'.
 */
class FreshRunner
{
    private PDO $pdo;

    /**
     * Module table ownership registry.
     * Format: 'table_name' => 'ModuleName'
     *
     * Add new tables here when creating new modules.
     */
    private const TABLE_OWNERSHIP = [
        // Auth
        'password_resets'           => 'Auth',
        'users'                     => 'Auth',

        // Product
        'product_daily_sales'       => 'Product',
        'products'                  => 'Product',
        'subcategories'             => 'Product',
        'categories'                => 'Product',

        // Customer
        'customer_payments'         => 'Customer',
        'customer_credits'          => 'Customer',

        // Billing
        'payments'                  => 'Billing',
        'bill_items'                => 'Billing',
        'bills'                     => 'Billing',

        // Vendor (no tables yet — placeholder)
    ];

    /** Tables that must never be dropped (infrastructure) */
    private const PROTECTED_TABLES = [
        'migration_history',
        'migrations',  // legacy tracking table
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Drop all module-owned tables and re-run all migrations.
     *
     * @throws \RuntimeException if run in production
     */
    public function run(): void
    {
        BackupGuard::blockInProduction('fresh');
        BackupGuard::check('fresh');

        MigrationLogger::heading('Running FRESH — dropping module-owned tables...');

        $ownedTables = $this->resolveOwnedTables();

        if (empty($ownedTables)) {
            MigrationLogger::info('No module-owned tables found to drop.');
        } else {
            $this->dropTables($ownedTables);
        }

        // Also clear migration_history so all migrations re-run
        $this->pdo->exec("TRUNCATE TABLE migration_history");
        MigrationLogger::info('Migration history cleared.');
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Discover all tables in public schema that belong to a registered module.
     *
     * @return string[]  Table names in drop-safe order (children before parents)
     */
    private function resolveOwnedTables(): array
    {
        $stmt = $this->pdo->query(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $owned     = [];

        foreach ($allTables as $table) {
            if (in_array($table, self::PROTECTED_TABLES, true)) {
                continue;
            }

            if (array_key_exists($table, self::TABLE_OWNERSHIP)) {
                $owned[] = $table;
            }
        }

        return $this->orderForDrop($owned);
    }

    /**
     * Order tables so foreign key children are dropped before parents.
     * Dependency order is derived from FK relationships.
     *
     * @param  string[] $tables
     * @return string[]
     */
    private function orderForDrop(array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));

        $stmt = $this->pdo->prepare("
            SELECT
                tc.table_name       AS child_table,
                ccu.table_name      AS parent_table
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.referential_constraints AS rc
                ON tc.constraint_name = rc.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
                ON rc.unique_constraint_name = ccu.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
              AND tc.table_name IN ({$placeholders})
        ");
        $stmt->execute($tables);

        $deps = [];
        foreach ($stmt->fetchAll() as $row) {
            $deps[$row['child_table']][] = $row['parent_table'];
        }

        // Topological sort (children first)
        $sorted  = [];
        $visited = [];

        $visit = function (string $table) use (&$visit, &$sorted, &$visited, $deps): void {
            if (isset($visited[$table])) {
                return;
            }
            $visited[$table] = true;
            foreach ($deps[$table] ?? [] as $dep) {
                $visit($dep);
            }
            $sorted[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        // Reverse: children first, parents last for DROP TABLE
        return array_reverse($sorted);
    }

    /**
     * @param string[] $tables
     */
    private function dropTables(array $tables): void
    {
        foreach ($tables as $table) {
            $module = self::TABLE_OWNERSHIP[$table] ?? 'Unknown';
            $this->pdo->exec("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
            MigrationLogger::info("Dropped: {$table}", ['module' => $module]);
        }
    }
}
