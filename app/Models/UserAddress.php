<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class UserAddress extends Model
{
    protected static string $table = 'user_addresses';

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM user_addresses WHERE user_id = :user_id ORDER BY is_default DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function belongsToUser(int $addressId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM user_addresses WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $addressId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }

    public static function clearDefault(int $userId): void
    {
        $stmt = self::db()->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
