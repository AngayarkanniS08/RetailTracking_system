<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Accumulates errors and warnings from the validation rule pipeline.
 *
 * Errors are blocking — execution will not proceed.
 * Warnings are informational — execution continues but they are displayed.
 */
class ValidationResult
{
    /** @var array<int, array{rule: string, message: string, file: string}> */
    private array $errors = [];

    /** @var array<int, array{rule: string, message: string, file: string}> */
    private array $warnings = [];

    public function addError(string $rule, string $message, string $file = ''): void
    {
        $this->errors[] = ['rule' => $rule, 'message' => $message, 'file' => $file];
    }

    public function addWarning(string $rule, string $message, string $file = ''): void
    {
        $this->warnings[] = ['rule' => $rule, 'message' => $message, 'file' => $file];
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /** @return array<int, array{rule: string, message: string, file: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<int, array{rule: string, message: string, file: string}> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function warningCount(): int
    {
        return count($this->warnings);
    }
}
