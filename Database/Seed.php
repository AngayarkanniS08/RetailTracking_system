<?php
declare(strict_types=1);

/**
 * Backward-compatible seeder shim.
 *
 * This file is kept for:
 *   - make seed
 *   - Legacy CI pipelines
 *
 * All logic is delegated to SeederRunner.
 * Do not add business logic here.
 */

$projectRoot = dirname(__DIR__);
$enginePath  = $projectRoot . '/Database/Engine';
$seedersPath = $projectRoot . '/Database/Seeders';

require_once $enginePath  . '/MigrationLogger.php';
require_once $enginePath  . '/EventDispatcher.php';
require_once $enginePath  . '/SeederRunner.php';
require_once $seedersPath . '/BaseSeeder.php';
require_once $projectRoot . '/config/Database.php';

// Auto-discover seeder files
foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($seedersPath)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' &&
        $file->getFilename() !== 'BaseSeeder.php' &&
        !str_contains($file->getPathname(), '_templates')) {
        require_once $file->getPathname();
    }
}

try {
    $pdo = \Config\Database::getConnection();
} catch (\Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $seederRunner = new \Database\Engine\SeederRunner($pdo);
    $seederRunner->run();
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
