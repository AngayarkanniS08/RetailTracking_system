<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Structured dual-channel logger for the migration engine.
 *
 * Writes to:
 *   1. STDOUT (human-readable, coloured console output)
 *   2. logs/migrations.log (JSON-structured, machine-readable)
 *
 * The JSON format integrates with observability platforms (Datadog, Loki, etc.)
 */
class MigrationLogger
{
    private static string $logFile = __DIR__ . '/../../logs/migrations.log';

    private const COLORS = [
        'info'    => "\033[32m",  // green
        'warn'    => "\033[33m",  // yellow
        'error'   => "\033[31m",  // red
        'debug'   => "\033[36m",  // cyan
        'reset'   => "\033[0m",
        'bold'    => "\033[1m",
        'dim'     => "\033[2m",
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (getenv('MIGRATION_DEBUG') === 'true') {
            self::write('DEBUG', $message, $context);
        }
    }

    public static function separator(): void
    {
        fwrite(STDOUT, self::COLORS['dim'] . str_repeat('─', 70) . self::COLORS['reset'] . PHP_EOL);
    }

    public static function heading(string $text): void
    {
        $c = self::COLORS;
        fwrite(STDOUT, $c['bold'] . PHP_EOL . "  {$text}" . PHP_EOL . $c['reset']);
        self::separator();
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private static function write(string $level, string $message, array $context): void
    {
        // 1. Console output
        $color  = self::COLORS[strtolower($level)] ?? '';
        $reset  = self::COLORS['reset'];
        $time   = date('H:i:s');
        $prefix = match ($level) {
            'INFO'  => '✅',
            'WARN'  => '⚠️ ',
            'ERROR' => '❌',
            'DEBUG' => '🔍',
            default => '  ',
        };
        fwrite(STDOUT, "{$color}[{$time}] {$prefix} {$message}{$reset}" . PHP_EOL);

        // 2. JSON-structured log
        self::appendJson($level, $message, $context);
    }

    private static function appendJson(string $level, string $message, array $context): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = json_encode(array_merge(
            [
                'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
                'level'       => strtolower($level),
                'message'     => $message,
                'executed_by' => self::executedBy(),
            ],
            $context
        ), JSON_UNESCAPED_SLASHES);

        file_put_contents(self::$logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function executedBy(): string
    {
        return getenv('CI_JOB_NAME')
            ?: getenv('GITHUB_ACTOR')
            ?: get_current_user()
            ?: 'unknown';
    }
}
