<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Ensures every new (non-legacy) migration has a paired rollback file.
 *
 * For each .up.sql file that is NOT a legacy migration,
 * a corresponding .down.sql must exist in the same directory.
 *
 * Legacy files (numeric prefix) are exempt — rollback is skipped with a warning.
 */
class RollbackRule implements RuleInterface
{
    public function name(): string
    {
        return 'RollbackRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        foreach ($plans as $plan) {
            $isLegacy     = $plan['is_legacy'] ?? false;
            $absolutePath = $plan['absolute_path'] ?? '';
            $file         = $plan['relative_path'] ?? '';

            // Only check .up.sql files
            if (!str_ends_with($absolutePath, '.up.sql')) {
                continue;
            }

            // Legacy files — warn but do not block
            if ($isLegacy) {
                $result->addWarning(
                    $this->name(),
                    "No rollback available for legacy migration (grandfathered): {$file}",
                    $file
                );
                continue;
            }

            // New migrations must have a paired .down.sql
            $downPath = str_replace('.up.sql', '.down.sql', $absolutePath);

            if (!file_exists($downPath)) {
                $result->addError(
                    $this->name(),
                    "Missing rollback file for: {$file}\n" .
                    "  Expected: " . str_replace('.up.sql', '.down.sql', $file) . "\n" .
                    "  Generate with: php scripts/migrate generate <Module> <name>",
                    $file
                );
            }
        }
    }
}
