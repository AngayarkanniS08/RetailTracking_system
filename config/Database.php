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
            $dbname   = getenv('DB_DATABASE') ?: 'retail_tracking';
            $username = getenv('DB_USERNAME') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$connection;
    }
}