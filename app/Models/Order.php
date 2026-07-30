<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Order extends Model
{
    protected static string $table = 'orders';

    public static function findByNumberForUser(string $orderNumber, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM orders WHERE order_number = :order_number AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['order_number' => $orderNumber, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }
}
