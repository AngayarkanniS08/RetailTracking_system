<?php
namespace Modules\Backup\DTO;

class StartBackupDTO
{
    public function __construct(
        public readonly string $userId
    ) {}
}
