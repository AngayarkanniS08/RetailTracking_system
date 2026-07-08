<?php
namespace Modules\Backup\DTO;

class BackupConfigDTO
{
    public function __construct(
        public readonly ?string $gdriveBackupFolderId,
        public readonly bool $scheduleEnabled,
        public readonly string $scheduleTime,
        public readonly int $retentionDaily,
        public readonly int $retentionWeekly,
        public readonly int $retentionMonthly
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gdriveBackupFolderId: $data['gdrive_backup_folder_id'] ?? null,
            scheduleEnabled: (bool)($data['schedule_enabled'] ?? false),
            scheduleTime: $data['schedule_time'] ?? '22:00',
            retentionDaily: (int)($data['retention_daily'] ?? 7),
            retentionWeekly: (int)($data['retention_weekly'] ?? 4),
            retentionMonthly: (int)($data['retention_monthly'] ?? 12)
        );
    }
}
