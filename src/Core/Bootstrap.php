<?php

namespace Core;

class Bootstrap
{
    public static function init(): void
    {
        self::loadEnv();

        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);

        $sessionPath = dirname(__DIR__, 2) . '/tmp/sessions';
        if (!is_dir($sessionPath) && !mkdir($sessionPath, 0777, true) && !is_dir($sessionPath)) {
            throw new \RuntimeException("Unable to create session directory: $sessionPath");
        }

        session_save_path($sessionPath);
        ini_set('session.cookie_lifetime', '28800');
        ini_set('session.gc_maxlifetime', '28800');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function loadEnv(): void
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim(trim($value), '"\'');
                    if (getenv($name) === false) {
                        putenv("$name=$value");
                        $_ENV[$name] = $value;
                        $_SERVER[$name] = $value;
                    }
                }
            }
        }
    }
}
