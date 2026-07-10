<?php
require_once __DIR__ . '/../src/vendor/autoload.php';

$configPath = __DIR__ . '/../config/Database.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$backupDir = __DIR__ . '/../backup';

if (!is_dir($backupDir)) {
    die("Error: backup/ directory not found at $backupDir\n");
}

$files = glob("$backupDir/*.sql.gz");
if (!$files) {
    die("No backup files found in backup/\n");
}

usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

echo "=== Local Backup Restore Utility ===\n\n";
echo "Available backups:\n";
foreach ($files as $i => $f) {
    $size = filesize($f);
    $date = date('Y-m-d H:i:s', filemtime($f));
    $name = basename($f);
    $sizeStr = $size > 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024, 1) . ' KB';
    printf("  %d) %s  (%s, %s)\n", $i + 1, $name, $date, $sizeStr);
}

echo "\nSelect file [1]: ";
$input = trim(fgets(STDIN));
$index = $input === '' ? 0 : ((int)$input) - 1;
if (!isset($files[$index])) {
    die("Invalid selection\n");
}

$selected = $files[$index];
echo "Selected: " . basename($selected) . "\n";

echo "Tables to exclude (comma-separated) [users]: ";
$excludeInput = trim(fgets(STDIN));
$excludeTables = $excludeInput === '' ? ['users'] : array_map('trim', explode(',', $excludeInput));

echo "\nExcluding tables: " . implode(', ', $excludeTables) . "\n";

echo "\nWARNING: This will replace ALL current data in retail_pos!\n";
echo "Type 'yes' to continue: ";
$confirm = trim(fgets(STDIN));
if ($confirm !== 'yes') {
    die("Aborted.\n");
}

echo "\nVerifying gzip integrity... ";
$gzTest = sprintf('gzip -t %s 2>/dev/null', escapeshellarg($selected));
exec($gzTest, $_, $code);
if ($code !== 0) {
    die("FAILED: Backup file is corrupt\n");
}
echo "OK\n";

echo "Decompressing and filtering tables...\n";

$sql = file_get_contents("compress.zlib://$selected");
if ($sql === false) {
    die("Failed to read backup file\n");
}

$lines = explode("\n", $sql);
$outputLines = [];
$skip = false;
$skipCommentBlock = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    $currentTable = null;
    if (preg_match("/^COPY public\.(\w+)\s+\(.*?\) FROM stdin;/", $trimmed, $m)) {
        $currentTable = $m[1];
    }
    if (preg_match("/^CREATE TABLE public\.(\w+)\s*\(/", $trimmed, $m)) {
        $currentTable = $m[1];
    }

    if ($currentTable && in_array($currentTable, $excludeTables)) {
        if (str_starts_with($trimmed, 'COPY public.')) {
            $skip = true;
        } elseif (str_starts_with($trimmed, 'CREATE TABLE public.')) {
            $skip = true;
        }
        if ($skip) continue;
    }

    if ($skip) {
        if ($trimmed === "\." || preg_match("/^\);/", $trimmed)) {
            $skip = false;
        }
        continue;
    }

    if (preg_match("/^-- Name: (\w+); Type: CONSTRAINT;/", $trimmed, $m)) {
        $tableCandidate = $m[1];
        $tableName = preg_replace('/_\w+$/', '', $tableCandidate);
        $found = false;
        foreach ($excludeTables as $et) {
            if (str_contains($tableCandidate, $et) || str_contains($tableCandidate, 'idx_' . $et)) {
                $skipCommentBlock = true;
                $found = true;
                break;
            }
        }
        if ($found) continue;
    }

    if ($skipCommentBlock) {
        if (preg_match("/^ALTER TABLE ONLY public\.\w+/", $trimmed) ||
            preg_match("/^CREATE INDEX idx_\w+ ON public\.\w+/", $trimmed)) {
            $skipCommentBlock = false;
            continue;
        }
        if (empty($trimmed)) continue;
        $skipCommentBlock = false;
    }

    foreach ($excludeTables as $et) {
        if (preg_match("/^ALTER TABLE ONLY public\.$et\b/", $trimmed)) {
            continue 2;
        }
        if (preg_match("/^\s+ADD CONSTRAINT {$et}_\w+/", $trimmed)) {
            continue 2;
        }
        if (preg_match("/^CREATE INDEX idx_{$et}_\w+ ON public\.{$et}\b/", $trimmed)) {
            continue 2;
        }
    }

    $outputLines[] = $line;
}

$filteredSql = implode("\n", $outputLines);

echo "Restoring database...\n";

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'retail_pos';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASSWORD') ?: 'admin123';

$tmpFile = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
file_put_contents($tmpFile, $filteredSql);

$cmd = sprintf(
    'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -q -f %s 2>&1',
    escapeshellarg($dbPass),
    escapeshellarg($dbHost),
    escapeshellarg($dbPort),
    escapeshellarg($dbUser),
    escapeshellarg($dbName),
    escapeshellarg($tmpFile)
);

$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

@unlink($tmpFile);

if ($returnCode !== 0) {
    echo "Restore completed with warnings:\n";
    foreach ($output as $line) echo "  $line\n";
} else {
    echo "Restore completed successfully.\n";
}

echo "\nFlushing cache...\n";
$cacheCmd = 'docker exec retail_valkey valkey-cli FLUSHALL 2>/dev/null';
$cacheOut = [];
$cacheCode = 0;
exec($cacheCmd, $cacheOut, $cacheCode);
if ($cacheCode === 0) {
    echo "Cache flushed.\n";
} else {
    echo "Note: Could not flush cache (not in Docker or Valkey not running)\n";
}

echo "\nVerifying restored tables...\n";
$tables = ['backup_config', 'backup_jobs', 'categories', 'customers', 'inventory_batches',
           'invoices', 'invoice_items', 'products', 'vendor_purchases', 'vendor_purchase_items', 'vendors'];

try {
    $db = \Config\Database::getConnection();
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) FROM \"{$table}\"");
        $count = (int)$stmt->fetchColumn();
        echo "  $table: $count rows\n";
    }
} catch (\Exception $e) {
    echo "  Verification error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
