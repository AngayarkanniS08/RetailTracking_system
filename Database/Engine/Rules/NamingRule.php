<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Validates that new migration filenames follow the required naming convention:
 *
 *   [14-digit UTC timestamp]_[snake_case_description].(up|down).sql
 *
 * Example: 20260729103015_add_phone_to_customers.up.sql
 *
 * Legacy files with numeric prefixes (001_xxx.sql) are exempt from this rule
 * — they are grandfathered and will not be renamed.
 */
class NamingRule implements RuleInterface
{
    /**
     * Pattern for new-style timestamped migrations.
     * 14-digit timestamp + underscore + at least one word + extension.
     */
    private const TIMESTAMP_PATTERN = '/^\d{14}_[a-z][a-z0-9_]+\.(up|down)\.sql$/';

    /**
     * Pattern for legacy numeric-prefixed migrations (grandfathered).
     */
    private const LEGACY_PATTERN = '/^\d{3}_[a-z0-9_]+\.sql$/';

    public function name(): string
    {
        return 'NamingRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        foreach ($plans as $plan) {
            $basename = basename($plan['relative_path'] ?? '');
            $isLegacy = $plan['is_legacy'] ?? false;

            if ($isLegacy) {
                // Legacy files are exempt — only warn if they violate legacy pattern
                if (!preg_match(self::LEGACY_PATTERN, $basename)) {
                    $result->addWarning(
                        $this->name(),
                        "Legacy file has non-standard name (grandfathered): {$basename}",
                        $plan['relative_path'] ?? ''
                    );
                }
                continue;
            }

            // New-style migrations must follow timestamp pattern
            if (!preg_match(self::TIMESTAMP_PATTERN, $basename)) {
                $result->addError(
                    $this->name(),
                    "Invalid migration filename: '{$basename}'\n" .
                    "  Expected format: 20260729103015_descriptive_name.up.sql\n" .
                    "  Use: php scripts/migrate generate <Module> <name>",
                    $plan['relative_path'] ?? ''
                );
            }
        }
    }
}
