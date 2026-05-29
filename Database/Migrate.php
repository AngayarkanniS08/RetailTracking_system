<?php

require_once __DIR__ . '/../config/database.php';

// Ensure $pdo is available (accept common alternative names from config)
if (!isset($pdo)) {
    if (isset($conn)) {
        $pdo = $conn;
    } elseif (isset($db)) {
        $pdo = $db;
    } else {
        fwrite(STDERR, "Database connection (\$pdo) not found in config/database.php\n");
        exit(1);
    }
}

$migrationPath = __DIR__ . '/Migrations';

$files = scandir($migrationPath);

foreach ($files as $file) {

    // Skip non-sql files
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
        continue;
    }

    echo "\nChecking: $file\n";

    // Check if already executed
    $checkQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM migrations WHERE filename = ?"
    );

    $checkQuery->execute([$file]);

    $alreadyExecuted = $checkQuery->fetchColumn();

    if ($alreadyExecuted) {

        echo "Skipping already executed migration\n";

        continue;
    }

    // Read SQL file
    $sql = file_get_contents($migrationPath . '/' . $file);

    try {

        // Execute SQL
        $pdo->exec($sql);

        // Store migration record
        $insertQuery = $pdo->prepare(
            "INSERT INTO migrations (filename) VALUES (?)"
        );

        $insertQuery->execute([$file]);

        echo "Migration executed successfully\n";

    } catch (PDOException $e) {

        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}

echo "\nAll migrations completed\n";
