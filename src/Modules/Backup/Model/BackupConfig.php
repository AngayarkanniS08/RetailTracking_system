<?php
namespace Modules\Backup\Model;

class BackupConfig
{
    public function __construct(
        public ?string $id,
        public string $userId,
        public ?string $gdriveRefreshToken,
        public ?string $gdriveBackupFolderId,
        public ?string $gdriveAuthEmail,
        public bool $scheduleEnabled,
        public string $scheduleTime,
        public int $retentionDaily,
        public int $retentionWeekly,
        public int $retentionMonthly,
        public ?string $lastBackupAt,
        public string $lastBackupStatus,
        public ?string $createdAt,
        public ?string $updatedAt
    ) {}
}
