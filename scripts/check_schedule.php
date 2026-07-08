<?php
// ── Backup Schedule Checker ───────────────────────────────────────────────────
// Runs every 60s inside the backup-scheduler container.
// If schedule_enabled + schedule_time matches + no backup today → enqueue job.

require_once __DIR__ . '/../src/vendor/autoload.php';

$configPath = __DIR__ . '/../config/Database.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

use Modules\Backup\Repository\BackupRepository;
use Modules\Backup\Model\BackupJob;
use Modules\Backup\Service\JobQueue;

try {
    $db = \Config\Database::getConnection();
    $repo = new BackupRepository();
    $queue = new JobQueue();

    $stmt = $db->query("
        SELECT user_id, schedule_time, last_backup_at
        FROM backup_config
        WHERE schedule_enabled = true
    ");

    $currentTime = date('H:i');
    $today = date('Y-m-d');

    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $scheduleTime = $row['schedule_time'];
        $lastBackupDay = $row['last_backup_at'] ? date('Y-m-d', strtotime($row['last_backup_at'])) : null;

        if ($scheduleTime <= $currentTime && $lastBackupDay !== $today) {
            $job = new BackupJob(
                id: null,
                userId: $row['user_id'],
                jobType: 'backup',
                status: 'pending',
                fileName: null,
                fileSize: null,
                errorMessage: null,
                createdAt: null,
                completedAt: null
            );
            $saved = $repo->createJob($job);
            $queue->enqueue($saved->id);
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled backup enqueued for user {$row['user_id']}, job {$saved->id}\n";
        }
    }
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Scheduler error: " . $e->getMessage() . "\n";
}
