<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Ensures no two migration files share the same UTC timestamp prefix.
 * Duplicate timestamps cause non-deterministic execution ordering.
 */
class DuplicateTimestampRule implements RuleInterface
{
    public function name(): string
    {
        return 'DuplicateTimestampRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        $seen = [];

        foreach ($plans as $plan) {
            $ts = $plan['timestamp'] ?? '';
            $file = $plan['relative_path'] ?? '';

            if ($ts === '') {
                continue; // Legacy files — handled by NamingRule
            }

            if (isset($seen[$ts])) {
                $result->addError(
                    $this->name(),
                    "Duplicate timestamp '{$ts}' found in:\n  - {$seen[$ts]}\n  - {$file}",
                    $file
                );
            } else {
                $seen[$ts] = $file;
            }
        }
    }
}
