<?php
namespace Modules\Backup\Controller;

use Modules\Backup\Service\BackupService;
use Modules\Backup\Service\JobQueue;
use Modules\Backup\Service\GoogleDriveService;
use Modules\Backup\Repository\BackupRepository;

class BackupController
{
    private BackupService $service;
    private JobQueue $queue;
    private BackupRepository $repo;

    public function __construct()
    {
        $this->repo = new BackupRepository();
        $this->service = new BackupService($this->repo);
        $this->queue = new JobQueue();
    }

    public function start(): void
    {
        header('Content-Type: application/json');
        $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        try {
            $job = $this->service->startBackup($userId);
            $this->queue->enqueue($job->id);
            $this->queue->setStatus($job->id, 'pending');
            $this->queue->setProgress($job->id, 'Backup queued');

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'job_id' => $job->id,
                'status' => 'pending'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function status(string $jobId): void
    {
        header('Content-Type: application/json');

        $status = $this->queue->getStatus($jobId);
        $progress = $this->queue->getProgress($jobId);
        $job = $this->repo->getJob($jobId);

        echo json_encode([
            'job_id' => $jobId,
            'status' => $status ?? ($job?->status ?? 'unknown'),
            'progress' => $progress ?? '',
            'file_name' => $job?->fileName,
            'file_size' => $job?->fileSize,
            'error_message' => $job?->errorMessage,
            'created_at' => $job?->createdAt,
            'completed_at' => $job?->completedAt
        ]);
    }

    public function files(): void
    {
        header('Content-Type: application/json');

        try {
            $files = $this->service->listBackupFiles();
            echo json_encode(['success' => true, 'files' => $files]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function restore(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $driveFileId = $input['drive_file_id'] ?? '';

        if (empty($driveFileId)) {
            http_response_code(422);
            echo json_encode(['error' => 'Backup file ID is required']);
            return;
        }

        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'retail_pos';
        $dbUser = getenv('DB_USER') ?: 'admin';
        $dbPass = getenv('DB_PASSWORD') ?: 'admin123';

        try {
            // Download backup from Drive
            $filePath = $this->service->downloadFromDrive($driveFileId);

            // Run restore synchronously
            $this->service->runRestore($filePath, $dbHost, $dbName, $dbUser, $dbPass);

            // Verify restored data
            $verification = $this->service->verifyRestore();

            @unlink($filePath);

            echo json_encode([
                'success' => true,
                'message' => 'Restore completed successfully',
                'verification' => $verification
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
