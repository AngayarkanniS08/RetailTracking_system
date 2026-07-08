<?php
namespace Modules\Backup\Repository\Contract;

use Modules\Backup\Model\BackupConfig;
use Modules\Backup\Model\BackupJob;

interface BackupRepositoryInterface
{
    public function getConfig(string $userId): ?BackupConfig;
    public function saveConfig(BackupConfig $config): void;
    public function createJob(BackupJob $job): BackupJob;
    public function updateJob(string $jobId, array $data): void;
    public function getJob(string $jobId): ?BackupJob;
    public function getLatestJob(string $userId, string $jobType): ?BackupJob;
    public function getPendingJobs(): array;
}
