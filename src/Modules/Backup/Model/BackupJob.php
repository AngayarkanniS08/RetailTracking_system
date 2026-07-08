<?php
namespace Modules\Backup\Model;

class BackupJob
{
    public function __construct(
        public ?string $id,
        public string $userId,
        public string $jobType,
        public string $status,
        public ?string $fileName,
        public ?int $fileSize,
        public ?string $errorMessage,
        public ?string $createdAt,
        public ?string $completedAt
    ) {}
}
