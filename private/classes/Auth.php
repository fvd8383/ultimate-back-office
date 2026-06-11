<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Otp.php';
require_once __DIR__ . '/Session.php';

final class Auth
{
    public static function createActiveUser(string $firstName, string $lastName, string $email): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = self::normalizeEmail($email);

        if ($firstName === '' || $lastName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('First name, last name, and a valid email are required.');
        }

        if (self::emailExists($email)) {
            throw new RuntimeException('An account with that email already exists.');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO users (first_name, last_name, email, phone, status, created_at, updated_at)
             VALUES (:first_name, :last_name, :email, NULL, :status, NOW(), NOW())'
        );
        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'status' => 'active',
        ]);

        return [
            'id' => (int) Database::connection()->lastInsertId(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'status' => 'active',
        ];
    }

    public static function requestLoginCode(string $email): array
    {
        $email = self::normalizeEmail($email);
        $message = 'If that email belongs to an active user, a login code has been prepared.';

        if ($email === '') {
            return ['message' => $message, 'display_code' => null];
        }

        $user = self::findActiveUserByEmail($email);

        if ($user === null) {
            return ['message' => $message, 'display_code' => null];
        }

        $code = Otp::createForUser((int) $user['id']);

        if (self::shouldDisplayOtp()) {
            return ['message' => $message, 'display_code' => $code];
        }

        return ['message' => 'If that email belongs to an active user, an email would be sent. Email sending is not configured yet.', 'display_code' => null];
    }

    public static function verifyLoginCode(string $email, string $code): bool
    {
        $email = self::normalizeEmail($email);
        $code = trim($code);

        if ($email === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $user = self::findActiveUserByEmail($email);

        if ($user === null) {
            return false;
        }

        $otp = Otp::findMatchingUnusedCode((int) $user['id'], $code);

        if ($otp === null) {
            return false;
        }

        Otp::markUsed((int) $otp['id']);
        Session::login((int) $user['id']);
        self::logSuccessfulLogin((int) $user['id']);

        return true;
    }

    public static function currentUser(): ?array
    {
        $userId = Session::userId();

        if ($userId === null) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, phone, status
             FROM users
             WHERE id = :id AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'id' => $userId,
            'status' => 'active',
        ]);

        $user = $statement->fetch();

        return $user ?: null;
    }

    public static function linkedBusinesses(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id,
                    b.business_name,
                    b.email,
                    b.phone,
                    b.city,
                    b.state,
                    bu.is_owner,
                    bu.status AS link_status,
                    r.name AS role_name
             FROM business_users bu
             INNER JOIN businesses b ON b.id = bu.business_id
             LEFT JOIN roles r ON r.id = bu.role_id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.business_name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    private static function findActiveUserByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, status
             FROM users
             WHERE LOWER(email) = LOWER(:email)
               AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'email' => $email,
            'status' => 'active',
        ]);

        $user = $statement->fetch();

        return $user ?: null;
    }

    private static function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email)'
        );
        $statement->execute(['email' => $email]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function logSuccessfulLogin(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_logins (user_id, login_at, ip_address, user_agent)
             VALUES (:user_id, NOW(), :ip_address, :user_agent)'
        );
        $statement->execute([
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function shouldDisplayOtp(): bool
    {
        $environment = strtolower((string) Database::config('APP_ENV', 'production'));

        return in_array($environment, ['development', 'local', 'staging'], true);
    }
}
