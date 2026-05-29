<?php
namespace Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'retail_pos';
            $user = getenv('DB_USER') ?: 'admin';
            $pass = getenv('DB_PASSWORD') ?: 'admin123';
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            try {
                self::$connection = new PDO($dsn, $user, $pass);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("DB error: " . $e->getMessage());
            }
        }
        return self::$connection;
    }

    /**
     * Sets the PostgreSQL session variable used by RLS policies.
     * Call once per request from AuthMiddleware after JWT decode.
     * Every query in this request is then automatically filtered to $userId's rows.
     */
    public static function setCurrentUser(string $userId): void {
        // set_config(key, value, is_local=false) → session-scoped for the whole request
        self::getConnection()
            ->prepare("SELECT set_config('app.current_user_id', ?, false)")
            ->execute([$userId]);
    }
}