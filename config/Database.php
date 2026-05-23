<?php
namespace Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $host = 'localhost';
            $port = '5432';
            $dbname = 'retail_pos';
            $user = 'admin';
            $pass = 'admin123';
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
}