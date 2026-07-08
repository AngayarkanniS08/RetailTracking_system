<?php
namespace Modules\Backup\Model;

class BackupFileInfo
{
    public function __construct(
        public string $driveFileId,
        public string $fileName,
        public int $fileSize,
        public string $createdTime
    ) {}
}
