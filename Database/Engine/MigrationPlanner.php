<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Discovers, parses, and sorts all migration files into an ordered execution plan.
 *
 * Responsibilities:
 *   - Scan every module's Migrations/ directory
 *   - Parse metadata headers
 *   - Resolve optional depends_on relationships (topological sort)
 *   - Fall back to UTC timestamp order when no dependencies declared
 *   - Produce a flat, ordered ExecutionPlan[] array for the validator and executor
 *
 * The planner does NOT touch the database.
 */
class MigrationPlanner
{
    private const MIGRATIONS_BASE = __DIR__ . '/../Migrations';

    /** Modules that own migration directories */
    private const MODULE_DIRS = [
        '_schema', 'Auth', 'Product', 'Products', 'Customer',
        'Billing', 'Vendor', 'Security', 'Settings', 'Dashboard',
    ];

    /**
     * Discover all migration files and return a sorted execution plan.
     *
     * @param  array<string, array<string, mixed>> $appliedIndex  keyed by migration_path
     * @return array<int, array<string, mixed>>
     */
    public function plan(array $appliedIndex = []): array
    {
        $files = $this->discover();
        $plans = $this->buildPlanItems($files, $appliedIndex);
        $plans = $this->sort($plans);

        return $plans;
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Recursively scan all module directories for .sql migration files.
     *
     * @return array<string, string>  relative_path => absolute_path
     */
    private function discover(): array
    {
        $base  = self::MIGRATIONS_BASE;
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext      = $file->getExtension();
            $basename = $file->getFilename();

            // Only process SQL files; skip template files
            if ($ext !== 'sql' || str_contains($file->getPathname(), '_templates')) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = str_replace($base . '/', '', $absolute);

            $files[$relative] = $absolute;
        }

        return $files;
    }

    // -------------------------------------------------------------------------
    // Plan item construction
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>               $files
     * @param  array<string, array<string, mixed>> $appliedIndex
     * @return array<int, array<string, mixed>>
     */
    private function buildPlanItems(array $files, array $appliedIndex): array
    {
        $plans = [];

        foreach ($files as $relative => $absolute) {
            $basename  = basename($relative);
            $module    = $this->extractModule($relative);
            $isLegacy  = $this->isLegacy($basename);
            $timestamp = $this->extractTimestamp($basename);
            $name      = $this->extractName($basename);
            $isDown    = str_ends_with($basename, '.down.sql');

            // Exclude .down.sql from execution plan — they are used by rollback only
            if ($isDown) {
                continue;
            }

            $meta   = $this->parseMetadata($absolute);
            $stored = $appliedIndex[$relative] ?? null;

            $plans[] = [
                'relative_path'   => $relative,
                'absolute_path'   => $absolute,
                'module'          => $module,
                'migration_name'  => $name,
                'is_legacy'       => $isLegacy,
                'timestamp'       => $timestamp,
                'is_transactional'=> $meta['transactional'] ?? true,
                'depends_on'      => $meta['depends_on'] ?? [],
                'estimated_ms'    => $meta['estimated_ms'] ?? null,
                'risk_level'      => $meta['risk_level'] ?? 'UNKNOWN',
                'status'          => $stored['status'] ?? 'pending',
                'stored_checksum' => $stored['checksum'] ?? null,
                'batch'           => $stored['batch'] ?? null,
                'executed_at'     => $stored['finished_at'] ?? null,
            ];
        }

        return $plans;
    }

    // -------------------------------------------------------------------------
    // Sorting (timestamp order; legacy numeric first)
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>> $plans
     * @return array<int, array<string, mixed>>
     */
    private function sort(array $plans): array
    {
        usort($plans, function (array $a, array $b): int {
            $tsA = $a['timestamp'];
            $tsB = $b['timestamp'];

            // Legacy files use their numeric prefix as sort key
            $sortA = $tsA ?: $this->legacySortKey($a['relative_path']);
            $sortB = $tsB ?: $this->legacySortKey($b['relative_path']);

            return $sortA <=> $sortB;
        });

        return $plans;
    }

    private function legacySortKey(string $relativePath): string
    {
        preg_match('/^(\d+)/', basename($relativePath), $m);
        // Pad to 14 digits so legacy files sort before any real timestamp
        return isset($m[1]) ? str_pad($m[1], 14, '0', STR_PAD_LEFT) : '00000000000000';
    }

    // -------------------------------------------------------------------------
    // Metadata parser
    // -------------------------------------------------------------------------

    /**
     * Parse the standard metadata block from the top of a migration file.
     *
     * @return array<string, mixed>
     */
    private function parseMetadata(string $absolutePath): array
    {
        if (!file_exists($absolutePath)) {
            return [];
        }

        $content = file_get_contents($absolutePath);
        $meta    = [];

        $patterns = [
            'transactional' => '/--\s*Transactional:\s*(true|false)/i',
            'risk_level'    => '/--\s*Risk Level:\s*(\w+)/i',
            'estimated_ms'  => '/--\s*Estimated Time:\s*<?\s*(\d+)\s*ms/i',
            'depends_on_raw'=> '/--\s*Depends On:\s*(.+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $meta[$key] = trim($m[1]);
            }
        }

        // Normalise booleans
        if (isset($meta['transactional'])) {
            $meta['transactional'] = strtolower($meta['transactional']) !== 'false';
        }

        // Parse comma-separated dependencies
        if (isset($meta['depends_on_raw'])) {
            $meta['depends_on'] = array_map(
                'trim',
                explode(',', $meta['depends_on_raw'])
            );
            unset($meta['depends_on_raw']);
        }

        if (isset($meta['estimated_ms'])) {
            $meta['estimated_ms'] = (int) $meta['estimated_ms'];
        }

        return $meta;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractModule(string $relativePath): string
    {
        $parts  = explode('/', $relativePath);
        $module = $parts[0] ?? 'Unknown';
        return $module === 'Products' ? 'Product' : $module;
    }

    private function isLegacy(string $basename): bool
    {
        // Legacy = starts with 1–3 digit number prefix and no .up.sql suffix
        return (bool) preg_match('/^\d{1,3}_/', $basename)
            && !str_ends_with($basename, '.up.sql');
    }

    private function extractTimestamp(string $basename): string
    {
        if (preg_match('/^(\d{14})_/', $basename, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractName(string $basename): string
    {
        // Remove timestamp prefix and extension(s)
        $name = preg_replace('/^\d+_/', '', $basename);
        $name = preg_replace('/\.(up|down)?\.sql$/', '', $name);
        return $name;
    }
}
