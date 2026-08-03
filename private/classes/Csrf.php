<?php

require_once __DIR__ . '/Session.php';

final class CsrfException extends RuntimeException
{
}

final class Csrf
{
    private const SESSION_KEY = 'csrf_tokens';
    private const TOKEN_BYTES = 32;
    private const MAX_AGE_SECONDS = 7200;

    public static function token(string $scope = 'default'): string
    {
        Session::start();
        $scope = self::normalizeScope($scope);
        $stored = $_SESSION[self::SESSION_KEY][$scope] ?? null;

        if (!is_array($stored)
            || !isset($stored['value'], $stored['created_at'])
            || !is_string($stored['value'])
            || (int) $stored['created_at'] < time() - self::MAX_AGE_SECONDS
        ) {
            return self::rotate($scope);
        }

        return $stored['value'];
    }

    public static function input(string $scope = 'default'): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token($scope), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    public static function validate($submittedToken, string $scope = 'default'): bool
    {
        Session::start();
        $scope = self::normalizeScope($scope);
        $stored = $_SESSION[self::SESSION_KEY][$scope] ?? null;

        if (!is_string($submittedToken)
            || !is_array($stored)
            || !isset($stored['value'], $stored['created_at'])
            || !is_string($stored['value'])
            || (int) $stored['created_at'] < time() - self::MAX_AGE_SECONDS
        ) {
            return false;
        }

        return hash_equals($stored['value'], $submittedToken);
    }

    public static function requireValid($submittedToken, string $scope = 'default'): void
    {
        if (!self::validate($submittedToken, $scope)) {
            throw new CsrfException('Your request could not be verified. Please refresh the page and try again.');
        }
    }

    public static function rotate(string $scope = 'default'): string
    {
        Session::start();
        $scope = self::normalizeScope($scope);
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $_SESSION[self::SESSION_KEY][$scope] = [
            'value' => $token,
            'created_at' => time(),
        ];

        return $token;
    }

    private static function normalizeScope(string $scope): string
    {
        $scope = trim($scope);

        return $scope !== '' ? substr($scope, 0, 100) : 'default';
    }
}
