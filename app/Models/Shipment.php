<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Shipment extends Model
{
    protected static string $table = 'shipments';

    public static function forOrder(int $orderId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM shipments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Creates the shipment row for an order if none exists yet,
     * otherwise updates the existing one - an order has at most one
     * active shipment in this schema.
     */
    public static function createOrUpdate(int $orderId, array $data): void
    {
        $existing = self::forOrder($orderId);

        if ($existing !== null) {
            self::update((int) $existing['id'], $data);

            return;
        }

        self::create(array_merge($data, ['order_id' => $orderId]));
    }
}
