<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Contract for all migration validation rules.
 *
 * Each rule is a single-responsibility check injected into MigrationValidator.
 * Rules receive the full planned migration set and append errors/warnings
 * to a shared ValidationResult. They never execute SQL.
 */
interface RuleInterface
{
    /**
     * Run the validation check against the planned migration set.
     *
     * @param  array<int, array<string, mixed>> $plans  Resolved migration plan items
     * @param  ValidationResult                 $result Accumulates errors and warnings
     */
    public function check(array $plans, ValidationResult $result): void;

    /**
     * Human-readable name for this rule (used in reports).
     */
    public function name(): string;
}
