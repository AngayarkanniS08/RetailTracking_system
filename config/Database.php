<?php
declare(strict_types=1);

namespace Config;

use PDO;
use Exception;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host     = getenv('DB_HOST') ?: '127.0.0.1';
            $port     = getenv('DB_PORT') ?: '5432';
            $dbname   = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: 'retail_pos';
            $username = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'admin';
            $password = getenv('DB_PASSWORD') ?: 'admin123';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$connection;
    }

    public static function setCurrentUser(string $userId): void
    {
        try {
            self::getConnection()->exec("SET app.current_user_id = " . self::getConnection()->quote($userId));
        } catch (\Throwable $e) {
            // Ignore session var set errors if RLS table session is optional
        }
    }
}