<?php
declare(strict_types=1);

namespace Database\Engine;

use PDO;
use Database\Seeders\BaseSeeder;

/**
 * Discovers and runs module-owned seeders.
 *
 * Seeders live in: Database/Seeders/{Module}/{Name}Seeder.php
 *
 * Discovery is automatic — add a seeder class and it runs.
 * Filtering by module is supported via --module flag.
 *
 * Lifecycle events:
 *   before.seed → (seeder runs) → after.seed
 */
class SeederRunner
{
    private const SEEDERS_BASE = __DIR__ . '/../Seeders';

    private PDO             $pdo;
    private EventDispatcher $events;

    public function __construct(PDO $pdo, EventDispatcher $events = null)
    {
        $this->pdo    = $pdo;
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Run all discovered seeders, optionally filtered by module.
     *
     * @param string|null $moduleFilter  Only run seeders for this module
     */
    public function run(?string $moduleFilter = null): void
    {
        $seeders = $this->discover($moduleFilter);

        if (empty($seeders)) {
            $filter = $moduleFilter ? " for module '{$moduleFilter}'" : '';
            MigrationLogger::info("No seeders found{$filter}.");
            return;
        }

        MigrationLogger::heading('Running ' . count($seeders) . ' seeder(s)...');

        foreach ($seeders as $seederClass) {
            $this->runOne($seederClass);
        }

        MigrationLogger::info('All seeders complete.');
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * @return string[]  Fully-qualified seeder class names
     */
    private function discover(?string $moduleFilter): array
    {
        $base     = self::SEEDERS_BASE;
        $seeders  = [];

        if (!is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $basename = $file->getFilename();

            // Skip base class and template
            if ($basename === 'BaseSeeder.php' || str_contains($file->getPathname(), '_templates')) {
                continue;
            }

            // Extract module from directory structure
            $relativePath = str_replace($base . '/', '', $file->getPathname());
            $parts        = explode('/', $relativePath);
            $module       = $parts[0] ?? '';

            if ($moduleFilter !== null && $module !== $moduleFilter) {
                continue;
            }

            // Build class name from path
            $className = 'Database\\Seeders\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $seeders[] = $className;
        }

        return $seeders;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    private function runOne(string $seederClass): void
    {
        if (!class_exists($seederClass)) {
            // Auto-require the file
            $filePath = self::SEEDERS_BASE . '/' .
                str_replace(['Database\\Seeders\\', '\\'], ['', '/'], $seederClass) . '.php';

            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }

        if (!class_exists($seederClass)) {
            MigrationLogger::warn("Seeder class not found: {$seederClass}");
            return;
        }

        /** @var BaseSeeder $seeder */
        $seeder = new $seederClass($this->pdo);

        $this->events->dispatch('before.seed', ['seeder' => $seederClass]);
        MigrationLogger::info("Running seeder: {$seederClass}");

        try {
            $seeder->run();
            $this->events->dispatch('after.seed', ['seeder' => $seederClass, 'status' => 'ok']);
        } catch (\Throwable $e) {
            MigrationLogger::error("Seeder failed: {$seederClass}\n  " . $e->getMessage());
            throw $e;
        }
    }
}
