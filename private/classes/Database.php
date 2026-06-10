<?php

final class Database
{
    private static ?PDO $connection = null;
    private static ?array $config = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::config();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            (int) $config['DB_PORT'],
            $config['DB_NAME']
        );

        $options = self::pdoOptions($config);

        self::$connection = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], $options);

        return self::$connection;
    }

    public static function config(?string $key = null, $default = null)
    {
        if (self::$config === null) {
            $path = dirname(__DIR__) . '/config/env.php';

            if (!file_exists($path)) {
                throw new RuntimeException('Missing private/config/env.php. Copy private/config/env.example.php and fill in the environment values.');
            }

            $config = require $path;

            if (!is_array($config)) {
                throw new RuntimeException('private/config/env.php must return an array.');
            }

            $required = [
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
                'DB_PASSWORD',
                'APP_ENV',
                'APP_DEBUG',
                'APP_BASE_URL',
                'ACCOUNTS_BASE_URL',
            ];

            foreach ($required as $name) {
                if (!array_key_exists($name, $config)) {
                    throw new RuntimeException("Missing environment value: {$name}");
                }
            }

            self::$config = $config;
            self::applyErrorDisplaySetting();
        }

        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    private static function pdoOptions(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $sslMode = strtoupper(trim((string) ($config['DB_SSL_MODE'] ?? '')));

        if ($sslMode !== 'REQUIRED') {
            return $options;
        }

        $sslCa = trim((string) ($config['DB_SSL_CA'] ?? ''));

        if ($sslCa !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $sslCa;
            return $options;
        }

        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }

        return $options;
    }

    private static function applyErrorDisplaySetting(): void
    {
        if ((bool) self::$config['APP_DEBUG']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            return;
        }

        ini_set('display_errors', '0');
    }
}
