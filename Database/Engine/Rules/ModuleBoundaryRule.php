<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Detects potential cross-module schema violations.
 *
 * Each module owns its own tables. A migration in module A should not
 * ALTER or DROP a table that belongs to module B.
 *
 * Detection heuristic: checks if a migration's SQL references table names
 * that are registered to a different module.
 *
 * Severity is environment-configurable:
 *   - APP_ENV=development → warning (allows experimentation)
 *   - APP_ENV=staging/production/ci → error (enforces discipline)
 *
 * Note: This is a best-effort heuristic. Complex SQL (views, CTEs) may
 * produce false positives. The goal is to catch obvious violations.
 */
class ModuleBoundaryRule implements RuleInterface
{
    /**
     * Known table → module ownership map.
     * Extend this map as new modules are added.
     */
    private const TABLE_OWNERSHIP = [
        'users'              => 'Auth',
        'password_resets'    => 'Auth',
        'categories'         => 'Product',
        'subcategories'      => 'Product',
        'products'           => 'Product',
        'customer_credits'   => 'Customer',
        'customer_payments'  => 'Customer',
        'bills'              => 'Billing',
        'bill_items'         => 'Billing',
        'payments'           => 'Billing',
    ];

    /** DDL verbs that constitute ownership-changing operations */
    private const DDL_PATTERN = '/\b(ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\s+([`"\']?)(\w+)\2/i';

    public function name(): string
    {
        return 'ModuleBoundaryRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        $isStrict = $this->isStrictMode();

        foreach ($plans as $plan) {
            $module       = $plan['module'] ?? '';
            $absolutePath = $plan['absolute_path'] ?? '';
            $file         = $plan['relative_path'] ?? '';

            // Infrastructure / system modules (Security, Settings, _schema) perform cross-cutting DDL by design
            if (in_array($module, ['Security', '_schema', 'Settings'], true)) {
                continue;
            }

            if (!file_exists($absolutePath)) {
                continue;
            }

            $sql = file_get_contents($absolutePath);

            preg_match_all(self::DDL_PATTERN, $sql, $matches);

            foreach ($matches[3] ?? [] as $tableName) {
                $tableName = strtolower($tableName);
                $owner     = self::TABLE_OWNERSHIP[$tableName] ?? null;

                if ($owner === null || strtolower($owner) === strtolower($module)) {
                    continue;
                }

                $msg = "Cross-module violation: module '{$module}' is modifying table '{$tableName}' owned by '{$owner}'.\n" .
                       "  File: {$file}\n" .
                       "  Cross-module schema changes must go through application services or events.";

                if ($isStrict) {
                    $result->addError($this->name(), $msg, $file);
                } else {
                    $result->addWarning($this->name(), $msg, $file);
                }
            }
        }
    }

    private function isStrictMode(): bool
    {
        $env = strtolower(getenv('APP_ENV') ?: 'development');
        return in_array($env, ['staging', 'production', 'ci'], true);
    }
}
