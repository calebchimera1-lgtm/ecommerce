<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrderItem extends Model
{
    protected static string $table = 'order_items';

    public static function forOrder(int $orderId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }
}
