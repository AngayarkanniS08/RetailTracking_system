<?php
declare(strict_types=1);

/**
 * Backward-compatible migration shim.
 *
 * This file is kept for:
 *   - Docker CMD: php Database/Migrate.php
 *   - make migrate
 *   - Legacy CI pipelines
 *
 * All logic is delegated to the MigrationRunner.
 * Do not add business logic here.
 */

$projectRoot = dirname(__DIR__);
$enginePath  = $projectRoot . '/Database/Engine';
$rulesPath   = $enginePath  . '/Rules';
$seedersPath = $projectRoot . '/Database/Seeders';

// Load engine
foreach ([
    $enginePath . '/MigrationLogger.php',
    $enginePath . '/ValidationResult.php',
    $enginePath . '/MigrationChecksum.php',
    $enginePath . '/EventDispatcher.php',
    $enginePath . '/BackupGuard.php',
    $enginePath . '/MigrationLock.php',
    $enginePath . '/MigrationRepository.php',
    $enginePath . '/MigrationPlanner.php',
    $enginePath . '/MigrationStatus.php',
    $enginePath . '/MigrationExecutor.php',
    $enginePath . '/FreshRunner.php',
    $enginePath . '/MigrationGenerator.php',
    $enginePath . '/SeederRunner.php',
    $enginePath . '/SeederGenerator.php',
    $enginePath . '/MigrationValidator.php',
    $enginePath . '/MigrationRunner.php',
    $rulesPath  . '/RuleInterface.php',
    $rulesPath  . '/NamingRule.php',
    $rulesPath  . '/DuplicateTimestampRule.php',
    $rulesPath  . '/RollbackRule.php',
    $rulesPath  . '/ChecksumRule.php',
    $rulesPath  . '/DependencyRule.php',
    $rulesPath  . '/ModuleBoundaryRule.php',
    $rulesPath  . '/DangerousSqlRule.php',
    $seedersPath . '/BaseSeeder.php',
] as $file) {
    require_once $file;
}

require_once $projectRoot . '/config/Database.php';

try {
    $pdo = \Config\Database::getConnection();
} catch (\Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $runner = new \Database\Engine\MigrationRunner($pdo);
    $runner->up();
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
