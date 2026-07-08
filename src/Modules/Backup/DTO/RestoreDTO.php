<?php
namespace Modules\Backup\DTO;

class RestoreDTO
{
    public function __construct(
        public readonly string $driveFileId,
        public readonly string $confirmPassword
    ) {}
}
