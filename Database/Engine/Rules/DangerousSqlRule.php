<?php
declare(strict_types=1);

namespace Database\Engine\Rules;

use Database\Engine\ValidationResult;

/**
 * Detects dangerous SQL operations that could cause data loss.
 *
 * Flags the following patterns unless they include protective guards:
 *   - DROP TABLE without IF EXISTS
 *   - TRUNCATE without a comment guard
 *   - DELETE FROM without a WHERE clause
 *   - DROP COLUMN without IF EXISTS
 *
 * Always a warning (never a blocking error) — some destructive operations
 * are legitimate (e.g., dropping a table as part of a deliberate schema change).
 * The developer must explicitly acknowledge these in code review.
 */
class DangerousSqlRule implements RuleInterface
{
    private const PATTERNS = [
        [
            'regex'   => '/\bDROP\s+TABLE\s+(?!IF\s+EXISTS)/i',
            'message' => "DROP TABLE without IF EXISTS — may fail if table does not exist.",
        ],
        [
            'regex'   => '/\bTRUNCATE\b/i',
            'message' => "TRUNCATE detected — removes all rows without rollback (in most contexts).",
        ],
        [
            'regex'   => '/\bDELETE\s+FROM\s+\w+\s*;/i',
            'message' => "DELETE FROM without WHERE clause — will remove all rows.",
        ],
        [
            'regex'   => '/\bDROP\s+COLUMN\s+(?!IF\s+EXISTS)/i',
            'message' => "DROP COLUMN without IF EXISTS — may fail if column does not exist.",
        ],
    ];

    public function name(): string
    {
        return 'DangerousSqlRule';
    }

    public function check(array $plans, ValidationResult $result): void
    {
        foreach ($plans as $plan) {
            $absolutePath = $plan['absolute_path'] ?? '';
            $file         = $plan['relative_path'] ?? '';

            if (!file_exists($absolutePath)) {
                continue;
            }

            $sql = file_get_contents($absolutePath);

            foreach (self::PATTERNS as $check) {
                if (preg_match($check['regex'], $sql)) {
                    $result->addWarning(
                        $this->name(),
                        $check['message'] . "\n  File: {$file}\n  Ensure this is intentional and reviewed.",
                        $file
                    );
                }
            }
        }
    }
}
