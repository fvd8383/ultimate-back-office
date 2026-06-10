<?php

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        session_name('UBO_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
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
}
