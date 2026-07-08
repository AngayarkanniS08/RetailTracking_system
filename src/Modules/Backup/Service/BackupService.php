<?php
namespace Modules\Backup\Service;

use Modules\Backup\Repository\Contract\BackupRepositoryInterface;
use Modules\Backup\Model\BackupJob;
use Modules\Backup\Model\BackupFileInfo;

class BackupService
{
    private BackupRepositoryInterface $repo;
    private GoogleDriveService $driveService;

    public function __construct(
        BackupRepositoryInterface $repo,
        ?GoogleDriveService $driveService = null
    ) {
        $this->repo = $repo;
        $this->driveService = $driveService ?: new GoogleDriveService($repo);
    }

    public function startBackup(string $userId): BackupJob
    {
        $job = new BackupJob(
            id: null,
            userId: $userId,
            jobType: 'backup',
            status: 'pending',
            fileName: null,
            fileSize: null,
            errorMessage: null,
            createdAt: null,
            completedAt: null
        );
        return $this->repo->createJob($job);
    }

    public function runDump(string $userId, string $jobId, string $dbHost, string $dbName, string $dbUser, string $dbPass): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "backup_{$timestamp}.sql.gz";
        $filePath = "/tmp/{$fileName}";

        $this->repo->updateJob($jobId, ['status' => 'dump', 'file_name' => $fileName]);

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -U %s -d %s --no-owner --no-acl 2>/dev/null | gzip > %s',
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
            $error = "pg_dump failed (code: $returnCode)";
            if (file_exists($filePath)) unlink($filePath);
            throw new \RuntimeException($error);
        }

        return $filePath;
    }

    public function uploadToDrive(string $filePath, string $userId): string
    {
        $config = $this->repo->getConfig($userId);
        if (!$config || !$config->gdriveRefreshToken) {
            throw new \RuntimeException("Google Drive not authenticated");
        }

        $folderId = $config->gdriveBackupFolderId;
        $driveFileId = $this->driveService->uploadFile($filePath, $folderId);
        return $driveFileId;
    }

    public function listBackupFiles(string $userId): array
    {
        $config = $this->repo->getConfig($userId);
        if (!$config || !$config->gdriveRefreshToken) {
            return [];
        }
        return $this->driveService->listBackupFiles($config->gdriveBackupFolderId);
    }

    public function downloadFromDrive(string $driveFileId, string $userId): string
    {
        $config = $this->repo->getConfig($userId);
        if (!$config || !$config->gdriveRefreshToken) {
            throw new \RuntimeException("Google Drive not authenticated");
        }

        $destPath = "/tmp/restore_{$driveFileId}.sql.gz";
        $this->driveService->downloadFile($driveFileId, $destPath);
        return $destPath;
    }

    public function runRestore(string $filePath, string $dbHost, string $dbName, string $dbUser, string $dbPass): void
    {
        $gzTest = sprintf('gzip -t %s 2>/dev/null', escapeshellarg($filePath));
        $testOutput = [];
        $testCode = 0;
        exec($gzTest, $testOutput, $testCode);
        if ($testCode !== 0) {
            throw new \RuntimeException("Backup file is corrupt: gzip integrity check failed");
        }

        $command = sprintf(
            'gunzip -c %s 2>/dev/null | PGPASSWORD=%s psql -h %s -U %s -d %s 2>/dev/null',
            escapeshellarg($filePath),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbName)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("Restore failed (code: $returnCode)");
        }
    }

    public function verifyRestore(): array
    {
        $tables = ['users', 'products', 'inventory_batches', 'invoices', 'categories', 'customers'];
        $db = \Config\Database::getConnection();
        $results = [];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM \"{$table}\"");
                $results[$table] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $results[$table] = -1;
            }
        }
        return $results;
    }

    public function enforceRetention(string $userId): void
    {
        $config = $this->repo->getConfig($userId);
        if (!$config || !$config->gdriveRefreshToken) return;

        $this->driveService->enforceRetention(
            $config->gdriveBackupFolderId,
            $config->retentionDaily,
            $config->retentionWeekly,
            $config->retentionMonthly
        );
    }

    public function cleanupLocalFiles(string $dir = '/tmp', string $prefix = 'backup_', int $keepCount = 3): void
    {
        $files = glob("{$dir}/{$prefix}*.sql.gz");
        if (!$files) return;
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $toDelete = array_slice($files, $keepCount);
        foreach ($toDelete as $f) {
            @unlink($f);
        }
    }

    public function checkDiskSpace(string $filePath): bool
    {
        $freeBytes = disk_free_space(dirname($filePath)) ?: 0;
        $estimatedGb = 2 * 1024 * 1024 * 1024;
        return $freeBytes > $estimatedGb;
    }

    public function startRestore(string $userId): BackupJob
    {
        $job = new BackupJob(
            id: null,
            userId: $userId,
            jobType: 'restore',
            status: 'pending',
            fileName: null,
            fileSize: null,
            errorMessage: null,
            createdAt: null,
            completedAt: null
        );
        return $this->repo->createJob($job);
    }
}
