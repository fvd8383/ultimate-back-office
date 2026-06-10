<?php

class Database
{
    private static $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $envPath = dirname(__DIR__) . '/config/env.php';

        if (!file_exists($envPath)) {
            throw new RuntimeException('Missing environment configuration. Copy private/config/env.example.php to private/config/env.php.');
        }

        $config = require $envPath;
        $database = $config['database'] ?? [];

        $host = $database['host'] ?? '';
        $port = (int) ($database['port'] ?? 3306);
        $name = $database['name'] ?? '';
        $charset = $database['charset'] ?? 'utf8mb4';
        $user = $database['user'] ?? '';
        $password = $database['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
