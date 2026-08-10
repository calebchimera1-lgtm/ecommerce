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

    /**
     * When an order most recently transitioned to 'delivered' - the
     * return window (Module 25) counts from this, not from
     * `orders.updated_at`, which changes on any order update and
     * would silently reset the window on an unrelated edit (e.g. an
     * admin adding a shipment note).
     */
    public static function deliveredAt(int $orderId): ?string
    {
        $stmt = self::db()->prepare(
            "SELECT created_at FROM order_status_history
             WHERE order_id = :order_id AND status = 'delivered'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row['created_at'];
    }
}
