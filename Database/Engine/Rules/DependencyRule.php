<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Validates optional inter-module dependencies declared in migration metadata.
 *
 * Migrations may declare:
 *   -- Depends On: Auth, Product
 *
 * This rule ensures that:
 *   1. All declared dependency modules exist (are registered)
 *   2. At least one applied migration exists for each dependency module
 *      (i.e., the module has been bootstrapped before this migration runs)
 *
 * The planner uses this information to sort migrations topologically.
 */
class DependencyRule implements RuleInterface
{
    /** Registered module names */
    private const KNOWN_MODULES = [
        'Auth', 'Product', 'Customer', 'Billing',
        'Vendor', 'Security', 'Settings', 'Dashboard',
    ];

    public function name(): string
    {
        return 'DependencyRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        // Build a set of modules that have at least one applied migration
        $appliedModules = [];
        foreach ($plans as $plan) {
            if (($plan['status'] ?? '') === 'applied') {
                $appliedModules[$plan['module'] ?? ''] = true;
            }
        }

        foreach ($plans as $plan) {
            $dependencies = $plan['depends_on'] ?? [];
            $file         = $plan['relative_path'] ?? '';

            if (empty($dependencies)) {
                continue;
            }

            foreach ($dependencies as $dep) {
                $dep = trim($dep);

                if (empty($dep) || strtolower($dep) === 'none') {
                    continue;
                }

                if (!in_array($dep, self::KNOWN_MODULES, true)) {
                    $result->addError(
                        $this->name(),
                        "Unknown dependency module '{$dep}' declared in: {$file}\n" .
                        "  Known modules: " . implode(', ', self::KNOWN_MODULES),
                        $file
                    );
                    continue;
                }

                if (!isset($appliedModules[$dep])) {
                    $result->addWarning(
                        $this->name(),
                        "Dependency module '{$dep}' has no applied migrations yet.\n" .
                        "  Migration: {$file}\n" .
                        "  Ensure '{$dep}' module migrations run before this one.",
                        $file
                    );
                }
            }
        }
    }
}
