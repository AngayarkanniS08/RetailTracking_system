<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Scaffolds timestamped migration file pairs (.up.sql + .down.sql).
 *
 * Generates:
 *   Database/Migrations/{Module}/20260729103015_{name}.up.sql
 *   Database/Migrations/{Module}/20260729103015_{name}.down.sql
 *
 * - Timestamp is UTC with sub-second jitter to prevent collisions
 *   when multiple developers generate migrations simultaneously.
 * - Module name is validated against the known module registry.
 * - Template metadata block is pre-filled.
 */
class MigrationGenerator
{
    private const MIGRATIONS_BASE = __DIR__ . '/../Migrations';

    private const KNOWN_MODULES = [
        'Auth', 'Product', 'Customer', 'Billing',
        'Vendor', 'Security', 'Settings', 'Dashboard',
    ];

    /**
     * Generate a paired migration file set.
     *
     * @param  string $module  Module name (case-sensitive, must be registered)
     * @param  string $name    Descriptive name in snake_case (e.g. "add_phone_to_customers")
     * @return array{up: string, down: string}  Absolute paths to created files
     */
    public function generate(string $module, string $name): array
    {
        $this->validateModule($module);
        $this->validateName($name);

        $timestamp = $this->uniqueTimestamp($module, $name);
        $slug      = strtolower(trim($name, '_'));
        $dir       = self::MIGRATIONS_BASE . "/{$module}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $upFile   = "{$dir}/{$timestamp}_{$slug}.up.sql";
        $downFile = "{$dir}/{$timestamp}_{$slug}.down.sql";

        if (file_exists($upFile)) {
            throw new \RuntimeException("Migration file already exists: {$upFile}");
        }

        $author = get_current_user() ?: 'unknown';
        $now    = gmdate('Y-m-d\TH:i:s\Z');

        file_put_contents($upFile,   $this->upTemplate($module, $slug, $author, $now));
        file_put_contents($downFile, $this->downTemplate($module, $slug, $now));

        MigrationLogger::info("Generated migration: {$timestamp}_{$slug}", [
            'module' => $module,
            'up'     => $upFile,
            'down'   => $downFile,
        ]);

        return ['up' => $upFile, 'down' => $downFile];
    }

    // -------------------------------------------------------------------------
    // Templates
    // -------------------------------------------------------------------------

    private function upTemplate(string $module, string $name, string $author, string $now): string
    {
        $humanName = ucwords(str_replace('_', ' ', $name));

        return <<<SQL
        -- ============================================================
        -- Module:          {$module}
        -- Migration Name:  {$name}
        -- Author:          {$author}
        -- Created:         {$now}
        -- Description:     {$humanName}
        -- Purpose:         TODO — describe business reason (e.g. JIRA-XXXX)
        -- Depends On:      none
        -- Risk Level:      LOW
        -- Transactional:   true
        -- Rollback:        Available (paired .down.sql)
        -- Estimated Time:  < 100ms
        -- ============================================================

        -- TODO: Add your UP migration SQL here.
        -- Rules:
        --   • One logical schema change per migration.
        --   • Use IF EXISTS / IF NOT EXISTS guards.
        --   • Use explicit CONSTRAINT names.
        --   • Create indexes CONCURRENTLY (if non-transactional).
        --   • Never combine unrelated operations.

        SQL;
    }

    private function downTemplate(string $module, string $name, string $now): string
    {
        return <<<SQL
        -- ============================================================
        -- Rollback for: {$name}
        -- Module:       {$module}
        -- Created:      {$now}
        -- ============================================================

        -- TODO: Add the exact REVERSE of the UP migration here.
        -- Rules:
        --   • Must fully undo the .up.sql changes.
        --   • Use IF EXISTS guards.
        --   • Never drop data without explicit confirmation.

        SQL;
    }

    // -------------------------------------------------------------------------
    // Timestamp
    // -------------------------------------------------------------------------

    /**
     * Generate a globally unique 14-digit UTC timestamp.
     * Adds 1-second jitter if a file with the same timestamp already exists.
     */
    private function uniqueTimestamp(string $module, string $name): string
    {
        $slug = strtolower($name);
        $base = self::MIGRATIONS_BASE . "/{$module}";

        $ts = gmdate('YmdHis');

        // Check for collision and increment until unique
        $offset = 0;
        while (
            file_exists("{$base}/{$ts}_{$slug}.up.sql") ||
            $this->timestampExistsGlobally($ts)
        ) {
            $offset++;
            $ts = gmdate('YmdHis', time() + $offset);
        }

        return $ts;
    }

    private function timestampExistsGlobally(string $ts): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::MIGRATIONS_BASE)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_starts_with($file->getFilename(), $ts . '_')) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function validateModule(string $module): void
    {
        if (!in_array($module, self::KNOWN_MODULES, true)) {
            throw new \InvalidArgumentException(
                "Unknown module: '{$module}'\n" .
                "Known modules: " . implode(', ', self::KNOWN_MODULES)
            );
        }
    }

    private function validateName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,}$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid migration name: '{$name}'\n" .
                "Use snake_case with at least 3 characters. Example: add_phone_to_customers"
            );
        }
    }
}
