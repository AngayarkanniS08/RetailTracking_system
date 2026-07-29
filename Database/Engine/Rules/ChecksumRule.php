<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\MigrationChecksum;
use Database\Engine\ValidationResult;

/**
 * Verifies that every applied migration file still matches its stored SHA-256 checksum.
 *
 * Executed migrations are immutable. File edits after application indicate
 * history tampering and must be blocked immediately.
 */
class ChecksumRule implements RuleInterface
{
    public function name(): string
    {
        return 'ChecksumRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        foreach ($plans as $plan) {
            $storedChecksum = $plan['stored_checksum'] ?? null;
            $absolutePath   = $plan['absolute_path'] ?? '';
            $file           = $plan['relative_path'] ?? '';

            // Ignore un-applied or empty stored checksums (empty occurs during initial legacy table import)
            if ($storedChecksum === null || $storedChecksum === '') {
                continue;
            }

            if (!file_exists($absolutePath)) {
                $result->addError(
                    $this->name(),
                    "Applied migration file is missing from disk: {$file}\n" .
                    "This indicates history corruption. Do not delete applied migrations.",
                    $file
                );
                continue;
            }

            $current = MigrationChecksum::compute($absolutePath);

            if (!hash_equals($storedChecksum, $current)) {
                $result->addError(
                    $this->name(),
                    "CHECKSUM MISMATCH — applied migration has been altered:\n" .
                    "  File:    {$file}\n" .
                    "  Stored:  {$storedChecksum}\n" .
                    "  Current: {$current}\n" .
                    "  Fix: php scripts/migrate repair --approve",
                    $file
                );
            }
        }
    }
}
