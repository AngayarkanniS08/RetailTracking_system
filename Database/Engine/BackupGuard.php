<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Verifies backup policy before destructive operations.
 *
 * Checked before: fresh, rollback, repair --approve
 *
 * Rules:
 *   - APP_ENV=development → always allowed (no backup required)
 *   - APP_ENV=testing     → allowed (CI handles its own state)
 *   - APP_ENV=staging     → requires MIGRATION_BACKUP_VERIFIED=true env var
 *   - APP_ENV=production  → requires MIGRATION_BACKUP_VERIFIED=true env var
 *
 * To mark a backup as verified in CI/CD pipelines:
 *   export MIGRATION_BACKUP_VERIFIED=true
 *
 * @throws \RuntimeException if backup policy is not satisfied
 */
class BackupGuard
{
    private const SAFE_ENVS = ['development', 'testing', 'local', ''];

    /**
     * @param string $operation Human-readable name of the destructive operation
     * @throws \RuntimeException
     */
    public static function check(string $operation): void
    {
        $env = strtolower(getenv('APP_ENV') ?: '');

        if (in_array($env, self::SAFE_ENVS, true)) {
            MigrationLogger::debug(
                "BackupGuard: skipping check for APP_ENV={$env}",
                ['operation' => $operation]
            );
            return;
        }

        // Non-development environments require explicit backup confirmation
        $verified = getenv('MIGRATION_BACKUP_VERIFIED');

        if ($verified !== 'true') {
            throw new \RuntimeException(
                "BACKUP POLICY VIOLATION\n" .
                "Operation '{$operation}' is destructive and requires a verified backup.\n\n" .
                "APP_ENV is set to: {$env}\n\n" .
                "To proceed, ensure a database backup exists and set:\n" .
                "  export MIGRATION_BACKUP_VERIFIED=true\n\n" .
                "In CI/CD pipelines, add this after your backup step."
            );
        }

        MigrationLogger::info(
            "BackupGuard: backup verified for {$operation}",
            ['env' => $env, 'operation' => $operation]
        );
    }

    /**
     * Block any execution when APP_ENV=production for dangerous commands.
     *
     * @throws \RuntimeException
     */
    public static function blockInProduction(string $command): void
    {
        $env = strtolower(getenv('APP_ENV') ?: '');

        if ($env === 'production') {
            throw new \RuntimeException(
                "PRODUCTION GUARD: '{$command}' is not allowed in production.\n" .
                "Set APP_ENV to something other than 'production' to run this command."
            );
        }
    }
}
