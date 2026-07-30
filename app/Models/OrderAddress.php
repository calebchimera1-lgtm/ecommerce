<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrderAddress extends Model
{
    protected static string $table = 'order_addresses';

    /**
     * @return array{billing?:array,shipping?:array}
     */
    public static function forOrder(int $orderId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM order_addresses WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);

        $byType = [];

        foreach ($stmt->fetchAll() as $row) {
            $byType[$row['type']] = $row;
        }

        return $byType;
    }
}
