<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Payment extends Model
{
    protected static string $table = 'payments';

    public static function forOrder(int $orderId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Marks the most recent payment record for an order as completed
     * (used for COD orders, where "paid" happens on delivery/collection
     * rather than at checkout). No-op if the order has no payment row.
     */
    public static function markCompleted(int $orderId): void
    {
        $payment = self::forOrder($orderId);

        if ($payment === null) {
            return;
        }

        self::update((int) $payment['id'], [
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
