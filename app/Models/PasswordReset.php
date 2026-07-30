<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    public static function invalidateFor(string $email): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM password_resets WHERE email = :email');
        $stmt->execute(['email' => $email]);
    }

    public static function issue(string $email, string $hashedToken, string $expiresAt): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)'
        );
        $stmt->execute(['email' => $email, 'token' => $hashedToken, 'expires_at' => $expiresAt]);
    }

    public static function findValidToken(string $email, string $hashedToken): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM password_resets WHERE email = :email AND token = :token AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['email' => $email, 'token' => $hashedToken]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
