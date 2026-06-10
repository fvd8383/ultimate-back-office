<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function register(string $firstName, string $lastName, string $email, string $password): array
    {
        $pdo = Database::getConnection();
        $email = strtolower(trim($email));

        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);

        if ($check->fetch()) {
            return [
                'success' => false,
                'message' => 'An account with that email already exists.',
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $statement = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)'
        );

        try {
            $statement->execute([
                'first_name' => trim($firstName),
                'last_name' => trim($lastName),
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return [
                    'success' => false,
                    'message' => 'An account with that email already exists.',
                ];
            }

            throw $exception;
        }

        return [
            'success' => true,
            'user_id' => (int) $pdo->lastInsertId(),
        ];
    }

    public static function login(string $email, string $password): bool
    {
        self::startSession();

        $pdo = Database::getConnection();
        $statement = $pdo->prepare(
            'SELECT id, first_name, last_name, email, password_hash FROM users WHERE email = :email AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name'] = $user['last_name'];
        $_SESSION['user_email'] = $user['email'];

        $loginStatement = $pdo->prepare(
            'INSERT INTO user_logins (user_id, ip_address, user_agent) VALUES (:user_id, :ip_address, :user_agent)'
        );
        $loginStatement->execute([
            'user_id' => (int) $user['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return true;
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();

        return !empty($_SESSION['user_id']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => (int) $_SESSION['user_id'],
            'first_name' => $_SESSION['user_first_name'] ?? '',
            'last_name' => $_SESSION['user_last_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
        ];
    }
}
