<?php

final class Session
{
    private static ?array $config = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        session_name(self::cookieName());
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => self::cookieDomain(),
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['authenticated_at'] = time();
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::userId() !== null;
    }

    public static function requireAuth(string $redirectTo): void
    {
        if (!self::isAuthenticated()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    private static function cookieDomain(): string
    {
        $configuredDomain = trim((string) (self::config('SESSION_COOKIE_DOMAIN') ?? ''));

        if ($configuredDomain !== '') {
            return $configuredDomain;
        }

        $appHost = self::hostFromUrl((string) (self::config('APP_BASE_URL') ?? ''));
        $accountsHost = self::hostFromUrl((string) (self::config('ACCOUNTS_BASE_URL') ?? ''));

        if ($appHost === '' || $accountsHost === '' || $appHost === $accountsHost) {
            return '';
        }

        if (self::isLocalHost($appHost) || self::isLocalHost($accountsHost)) {
            return '';
        }

        $appParts = array_reverse(explode('.', $appHost));
        $accountParts = array_reverse(explode('.', $accountsHost));
        $common = [];

        foreach ($appParts as $index => $part) {
            if (($accountParts[$index] ?? null) !== $part) {
                break;
            }

            $common[] = $part;
        }

        if (count($common) < 2) {
            return '';
        }

        return '.' . implode('.', array_reverse($common));
    }

    private static function cookieName(): string
    {
        $configuredName = trim((string) (self::config('SESSION_COOKIE_NAME') ?? ''));

        return $configuredName !== '' ? $configuredName : 'UBO_SHARED_SESSION';
    }

    private static function config(string $key)
    {
        if (self::$config === null) {
            $path = dirname(__DIR__) . '/config/env.php';
            self::$config = file_exists($path) ? (array) require $path : [];
        }

        return self::$config[$key] ?? null;
    }

    private static function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
