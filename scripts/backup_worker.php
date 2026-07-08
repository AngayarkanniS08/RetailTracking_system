<?php
// ── Backup Worker Daemon ──────────────────────────────────────────────────────
// Runs as a long-lived process inside the backup-worker container.
// Picks jobs from the Valkey queue and processes them.

require_once __DIR__ . '/../src/vendor/autoload.php';

// Bootstrap database config
$configPath = __DIR__ . '/../config/Database.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

use Modules\Backup\Service\BackupJobRunner;
use Modules\Backup\Service\JobQueue;

$runner = new BackupJobRunner();
$queue = new JobQueue();

while (true) {
    try {
        $jobId = $queue->dequeue(30);
        if ($jobId) {
            echo "[" . date('Y-m-d H:i:s') . "] Processing job: {$jobId}\n";
            $runner->process($jobId);
            echo "[" . date('Y-m-d H:i:s') . "] Job {$jobId} done\n";
        }
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
        sleep(5);
    }
}
