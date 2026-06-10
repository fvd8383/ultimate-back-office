<?php

require_once __DIR__ . '/Database.php';

final class Otp
{
    public const PURPOSE_LOGIN = 'login';
    private const TTL_SECONDS = 600;

    public static function createForUser(int $userId, string $purpose = self::PURPOSE_LOGIN): string
    {
        $code = (string) random_int(100000, 999999);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        $statement = Database::connection()->prepare(
            'INSERT INTO user_otps (user_id, code_hash, purpose, expires_at, ip_address, user_agent, created_at)
             VALUES (:user_id, :code_hash, :purpose, :expires_at, :ip_address, :user_agent, NOW())'
        );
        $statement->execute([
            'user_id' => $userId,
            'code_hash' => $codeHash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);

        return $code;
    }

    public static function findMatchingUnusedCode(int $userId, string $code, string $purpose = self::PURPOSE_LOGIN): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, code_hash
             FROM user_otps
             WHERE user_id = :user_id
               AND purpose = :purpose
               AND used_at IS NULL
               AND expires_at >= NOW()
             ORDER BY created_at DESC
             LIMIT 5'
        );
        $statement->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
        ]);

        foreach ($statement->fetchAll() as $otp) {
            if (password_verify($code, $otp['code_hash'])) {
                return $otp;
            }
        }

        return null;
    }

    public static function markUsed(int $otpId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE user_otps
             SET used_at = NOW()
             WHERE id = :id AND used_at IS NULL'
        );
        $statement->execute(['id' => $otpId]);
    }
}
