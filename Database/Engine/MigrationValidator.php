<?php
declare(strict_types=1);

namespace Database\Engine;

use Database\Engine\Rules\RuleInterface;

/**
 * Composes and runs all registered validation rules against the execution plan.
 *
 * Rules are injected — new rules can be added without modifying this class.
 * Validation collects all errors and warnings before aborting, so developers
 * see every issue in a single run rather than one at a time.
 *
 * Validation is always run before execution (up, dry-run, rollback).
 */
class MigrationValidator
{
    /** @var RuleInterface[] */
    private array $rules;

    /**
     * @param RuleInterface[] $rules  Ordered list of validation rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules ?: $this->defaultRules();
    }

    /**
     * Run all rules against the plan set.
     *
     * @param  array<int, array<string, mixed>> $plans
     * @throws \RuntimeException if any blocking errors are found
     */
    public function validate(array $plans): ValidationResult
    {
        $result = new ValidationResult();

        MigrationLogger::heading('Running validation pipeline...');

        foreach ($this->rules as $rule) {
            $rule->check($plans, $result);
        }

        $this->report($result);

        if ($result->hasErrors()) {
            throw new \RuntimeException(
                sprintf(
                    "Validation failed: %d error(s), %d warning(s). Execution aborted.",
                    $result->errorCount(),
                    $result->warningCount()
                )
            );
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    private function report(ValidationResult $result): void
    {
        foreach ($result->getWarnings() as $w) {
            MigrationLogger::warn("[{$w['rule']}] {$w['message']}");
        }

        foreach ($result->getErrors() as $e) {
            MigrationLogger::error("[{$e['rule']}] {$e['message']}");
        }

        $total = count($this->rules);
        $errors   = $result->errorCount();
        $warnings = $result->warningCount();

        if ($errors === 0 && $warnings === 0) {
            MigrationLogger::info("Validation passed: {$total} rules checked, 0 issues.");
        } else {
            MigrationLogger::info(
                "Validation complete: {$total} rules, {$errors} error(s), {$warnings} warning(s)."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Default rule set
    // -------------------------------------------------------------------------

    /** @return RuleInterface[] */
    private function defaultRules(): array
    {
        return [
            new Rules\NamingRule(),
            new Rules\DuplicateTimestampRule(),
            new Rules\RollbackRule(),
            new Rules\ChecksumRule(),
            new Rules\DependencyRule(),
            new Rules\ModuleBoundaryRule(),
            new Rules\DangerousSqlRule(),
        ];
    }
}
