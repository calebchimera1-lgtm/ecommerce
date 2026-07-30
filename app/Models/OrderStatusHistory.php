<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrderStatusHistory extends Model
{
    protected static string $table = 'order_status_history';

    public static function forOrder(int $orderId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM order_status_history WHERE order_id = :order_id ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }
}
