<?php
namespace Modules\Backup\Repository;

use PDO;
use Modules\Backup\Model\BackupConfig;
use Modules\Backup\Model\BackupJob;
use Modules\Backup\Repository\Contract\BackupRepositoryInterface;

class BackupRepository implements BackupRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function getConfig(string $userId): ?BackupConfig
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, gdrive_refresh_token, gdrive_backup_folder_id,
                   gdrive_auth_email, schedule_enabled, schedule_time,
                   retention_daily, retention_weekly, retention_monthly,
                   last_backup_at, last_backup_status, created_at, updated_at
            FROM backup_config
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $this->hydrateConfig($row);
    }

    public function saveConfig(BackupConfig $config): void
    {
        $existing = $this->getConfig($config->userId);
        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE backup_config
                SET gdrive_refresh_token = COALESCE(?, gdrive_refresh_token),
                    gdrive_backup_folder_id = COALESCE(?, gdrive_backup_folder_id),
                    gdrive_auth_email = COALESCE(?, gdrive_auth_email),
                    schedule_enabled = ?,
                    schedule_time = ?,
                    retention_daily = ?,
                    retention_weekly = ?,
                    retention_monthly = ?,
                    updated_at = now()
                WHERE user_id = ?
            ");
            $stmt->execute([
                $config->gdriveRefreshToken,
                $config->gdriveBackupFolderId,
                $config->gdriveAuthEmail,
                $config->scheduleEnabled ? 'true' : 'false',
                $config->scheduleTime,
                $config->retentionDaily,
                $config->retentionWeekly,
                $config->retentionMonthly,
                $config->userId
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO backup_config (
                    id, user_id, gdrive_refresh_token, gdrive_backup_folder_id,
                    gdrive_auth_email, schedule_enabled, schedule_time,
                    retention_daily, retention_weekly, retention_monthly,
                    created_at, updated_at
                ) VALUES (
                    gen_random_uuid(), ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    now(), now()
                )
            ");
            $stmt->execute([
                $config->userId,
                $config->gdriveRefreshToken,
                $config->gdriveBackupFolderId,
                $config->gdriveAuthEmail,
                $config->scheduleEnabled ? 'true' : 'false',
                $config->scheduleTime,
                $config->retentionDaily,
                $config->retentionWeekly,
                $config->retentionMonthly
            ]);
        }
    }

    public function createJob(BackupJob $job): BackupJob
    {
        $stmt = $this->db->prepare("
            INSERT INTO backup_jobs (id, user_id, job_type, status, file_name, file_size, error_message, created_at)
            VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?, now())
            RETURNING id, user_id, job_type, status, file_name, file_size, error_message, created_at, completed_at
        ");
        $stmt->execute([
            $job->userId,
            $job->jobType,
            $job->status,
            $job->fileName,
            $job->fileSize,
            $job->errorMessage
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->hydrateJob($row);
    }

    public function updateJob(string $jobId, array $data): void
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        if (empty($sets)) return;
        $stmt = $this->db->prepare("
            UPDATE backup_jobs SET " . implode(', ', $sets) . ", completed_at = CASE WHEN ? IN ('completed','failed') THEN now() ELSE completed_at END
            WHERE id = ?
        ");
        $params[] = $data['status'] ?? '';
        $params[] = $jobId;
        $stmt->execute($params);
    }

    public function getJob(string $jobId): ?BackupJob
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, job_type, status, file_name, file_size, error_message, created_at, completed_at
            FROM backup_jobs WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateJob($row) : null;
    }

    public function getLatestJob(string $userId, string $jobType): ?BackupJob
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, job_type, status, file_name, file_size, error_message, created_at, completed_at
            FROM backup_jobs
            WHERE user_id = ? AND job_type = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $jobType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateJob($row) : null;
    }

    public function getPendingJobs(): array
    {
        $stmt = $this->db->query("
            SELECT id, user_id, job_type, status, file_name, file_size, error_message, created_at, completed_at
            FROM backup_jobs WHERE status = 'pending'
            ORDER BY created_at ASC LIMIT 5
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrateJob($r), $rows);
    }

    private function hydrateConfig(array $row): BackupConfig
    {
        return new BackupConfig(
            id: $row['id'],
            userId: $row['user_id'],
            gdriveRefreshToken: $row['gdrive_refresh_token'],
            gdriveBackupFolderId: $row['gdrive_backup_folder_id'],
            gdriveAuthEmail: $row['gdrive_auth_email'],
            scheduleEnabled: (bool)$row['schedule_enabled'],
            scheduleTime: $row['schedule_time'],
            retentionDaily: (int)$row['retention_daily'],
            retentionWeekly: (int)$row['retention_weekly'],
            retentionMonthly: (int)$row['retention_monthly'],
            lastBackupAt: $row['last_backup_at'],
            lastBackupStatus: $row['last_backup_status'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    private function hydrateJob(array $row): BackupJob
    {
        return new BackupJob(
            id: $row['id'],
            userId: $row['user_id'],
            jobType: $row['job_type'],
            status: $row['status'],
            fileName: $row['file_name'],
            fileSize: $row['file_size'] ? (int)$row['file_size'] : null,
            errorMessage: $row['error_message'],
            createdAt: $row['created_at'],
            completedAt: $row['completed_at']
        );
    }
}
