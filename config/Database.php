<?php
namespace Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    
    /**
     * Load environment variables from .env file if it exists.
     */
    private static function loadEnv(): void {
        $envPath = __DIR__ . '/../.env';
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

    public static function getConnection(): PDO {
        if (self::$connection === null) {
            self::loadEnv();

            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'retail_pos';
            $user = getenv('DB_USER') ?: 'admin';
            $pass = getenv('DB_PASSWORD') ?: 'admin123';
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            
            $maxRetries = 5;
            $retryDelay = 2; // seconds
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    self::$connection = new PDO($dsn, $user, $pass);
                    self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    break;
                } catch (PDOException $e) {
                    if ($attempt === $maxRetries) {
                        die("DB connection error after $maxRetries attempts: " . $e->getMessage());
                    }
                    if (php_sapi_name() === 'cli') {
                        echo "Database connection attempt $attempt failed. Retrying in $retryDelay seconds...\n";
                    }
                    sleep($retryDelay);
                }
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

    /**
     * Reads back the user ID set by setCurrentUser().
     * Returns null if not set (unauthenticated request).
     */
    public static function getCurrentUser(): ?string {
        $stmt = self::getConnection()
            ->query("SELECT current_setting('app.current_user_id', true)");
        $value = $stmt ? $stmt->fetchColumn() : null;
        return ($value !== '' && $value !== null) ? (string) $value : null;
    }
}