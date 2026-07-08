<?php
namespace Modules\Backup\Service;

use Modules\Backup\Repository\Contract\BackupRepositoryInterface;
use Core\Cache\ValkeyCache;

class BackupJobRunner
{
    private BackupRepositoryInterface $repo;
    private BackupService $service;
    private JobQueue $queue;

    public function __construct()
    {
        $this->repo = new \Modules\Backup\Repository\BackupRepository();
        $this->service = new BackupService($this->repo);
        $this->queue = new JobQueue();
    }

    public function process(string $jobId): void
    {
        $job = $this->repo->getJob($jobId);
        if (!$job) return;
        if ($job->status !== 'pending') return;

        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'retail_pos';
        $dbUser = getenv('DB_USER') ?: 'admin';
        $dbPass = getenv('DB_PASSWORD') ?: 'admin123';

        try {
            if ($job->jobType === 'backup') {
                $this->runBackupJob($jobId, $job->userId, $dbHost, $dbName, $dbUser, $dbPass);
            } elseif ($job->jobType === 'restore') {
                $this->runRestoreJob($jobId, $job->userId, $dbHost, $dbName, $dbUser, $dbPass);
            }
        } catch (\Exception $e) {
            $this->repo->updateJob($jobId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            $this->queue->setStatus($jobId, 'failed');
            $this->queue->setProgress($jobId, $e->getMessage());
        }
    }

    private function runBackupJob(string $jobId, string $userId, string $dbHost, string $dbName, string $dbUser, string $dbPass): void
    {
        $this->queue->setStatus($jobId, 'dump');
        $this->queue->setProgress($jobId, 'Dumping database...');

        $filePath = $this->service->runDump($userId, $jobId, $dbHost, $dbName, $dbUser, $dbPass);

        $this->queue->setStatus($jobId, 'uploading');
        $this->queue->setProgress($jobId, 'Uploading to Google Drive...');

        $driveFileId = $this->service->uploadToDrive($filePath, $userId);

        $this->queue->setProgress($jobId, 'Cleaning up old backups...');
        $this->service->enforceRetention($userId);
        $this->service->cleanupLocalFiles();

        @unlink($filePath);

        $this->repo->updateJob($jobId, [
            'status' => 'completed',
            'file_size' => filesize($filePath) ?: null,
            'error_message' => null
        ]);
        $this->queue->setStatus($jobId, 'completed');
        $this->queue->setProgress($jobId, 'Backup completed successfully');
    }

    private function runRestoreJob(string $jobId, string $userId, string $dbHost, string $dbName, string $dbUser, string $dbPass): void
    {
        $job = $this->repo->getJob($jobId);
        $driveFileId = $job->fileName;

        $this->queue->setStatus($jobId, 'downloading');
        $this->queue->setProgress($jobId, 'Downloading backup from Google Drive...');

        $filePath = $this->service->downloadFromDrive($driveFileId, $userId);

        $this->queue->setStatus($jobId, 'restoring');
        $this->queue->setProgress($jobId, 'Restoring database...');

        $this->service->runRestore($filePath, $dbHost, $dbName, $dbUser, $dbPass);

        $this->queue->setProgress($jobId, 'Verifying restored data...');
        $verification = $this->service->verifyRestore();

        @unlink($filePath);

        $this->repo->updateJob($jobId, [
            'status' => 'completed',
            'error_message' => null
        ]);
        $this->queue->setStatus($jobId, 'completed');
        $this->queue->setProgress($jobId, 'Restore completed. ' . json_encode($verification));
    }
}
