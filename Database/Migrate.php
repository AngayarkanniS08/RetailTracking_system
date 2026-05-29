<?php

require_once __DIR__ . '/../config/Database.php';

try {
    $pdo = \Config\Database::getConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Create migrations tracking table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) UNIQUE NOT NULL,
    executed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
)");

$migrationPath = __DIR__ . '/Migrations';

// Recursive SQL file finder
function getMigrationFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'sql') {
            // Get path relative to the Migrations folder
            $relativePath = str_replace($dir . '/', '', $file->getPathname());
            $files[$relativePath] = $file->getPathname();
        }
    }
    ksort($files); // Sort by relative path to execute in order (000_, 001_, etc.)
    return $files;
}

$migrations = getMigrationFiles($migrationPath);

foreach ($migrations as $relativeFile => $absolutePath) {

    echo "\nChecking: $relativeFile\n";

    // Check if already executed
    $checkQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM migrations WHERE filename = ?"
    );

    $checkQuery->execute([$relativeFile]);

    $alreadyExecuted = $checkQuery->fetchColumn();

    if ($alreadyExecuted) {
        echo "Skipping already executed migration\n";
        continue;
    }

    // Read SQL file
    $sql = file_get_contents($absolutePath);

    try {
        // Execute SQL
        $pdo->exec($sql);

        // Store migration record
        $insertQuery = $pdo->prepare(
            "INSERT INTO migrations (filename) VALUES (?)"
        );

        $insertQuery->execute([$relativeFile]);

        echo "Migration executed successfully\n";

    } catch (PDOException $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nAll migrations completed\n";
