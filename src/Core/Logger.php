<?php
declare(strict_types=1);

namespace Core;

class Logger
{
    private static string $logFile = __DIR__ . '/../../logs/app.log';

    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $date = date('Y-m-d H:i:s');
        $ctx = !empty($context) ? ' ' . json_encode($context) : '';
        $line = "[{$date}] [{$level}] {$message}{$ctx}" . PHP_EOL;

        file_put_contents(self::$logFile, $line, FILE_APPEND);
    }

    public static function info(string $msg, array $ctx = []): void
    {
        self::log('INFO', $msg, $ctx);
    }

    public static function error(string $msg, array $ctx = []): void
    {
        self::log('ERROR', $msg, $ctx);
    }

    public static function debug(string $msg, array $ctx = []): void
    {
        self::log('DEBUG', $msg, $ctx);
    }
}
