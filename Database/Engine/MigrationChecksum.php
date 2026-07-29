<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Computes and verifies SHA-256 checksums for migration files.
 *
 * Executed migrations are IMMUTABLE. If a file's checksum differs
 * from the stored value, execution is blocked.
 */
class MigrationChecksum
{
    /**
     * Compute the SHA-256 checksum of a migration file.
     */
    public static function compute(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("Migration file not found: {$absolutePath}");
        }

        return hash('sha256', file_get_contents($absolutePath));
    }

    /**
     * Verify that a file's current checksum matches the stored one.
     *
     * @throws \RuntimeException if checksum drift is detected
     */
    public static function verify(string $absolutePath, string $storedChecksum): void
    {
        $current = self::compute($absolutePath);

        if (!hash_equals($storedChecksum, $current)) {
            throw new \RuntimeException(
                "CHECKSUM MISMATCH — executed migration file has been altered.\n" .
                "  File:    {$absolutePath}\n" .
                "  Stored:  {$storedChecksum}\n" .
                "  Current: {$current}\n" .
                "Executed migrations are immutable. Do not edit applied files.\n" .
                "If this was intentional, run: php scripts/migrate repair --approve"
            );
        }
    }
}
