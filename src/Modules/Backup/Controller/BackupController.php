<?php
namespace Modules\Backup\Controller;

use Core\Middlewares\AuthMiddleware;
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
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';

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
        AuthMiddleware::authenticate();

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
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';

        try {
            $files = $this->service->listBackupFiles($userId);
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

        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';
        $input = json_decode(file_get_contents('php://input'), true);

        $driveFileId = $input['drive_file_id'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($driveFileId)) {
            http_response_code(422);
            echo json_encode(['error' => 'Backup file ID is required']);
            return;
        }

        $stmt = \Config\Database::getConnection()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($confirmPassword, $userRow['password_hash'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid admin password. Restore denied.']);
            return;
        }

        try {
            $job = $this->service->startRestore($userId);
            $this->repo->updateJob($job->id, ['file_name' => $driveFileId]);
            $this->queue->enqueue($job->id);
            $this->queue->setStatus($job->id, 'pending');
            $this->queue->setProgress($job->id, 'Restore queued');

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'job_id' => $job->id,
                'status' => 'pending',
                'message' => 'Restore started. This will replace all current data.'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
