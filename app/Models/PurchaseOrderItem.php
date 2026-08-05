<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PurchaseOrderItem extends Model
{
    protected static string $table = 'purchase_order_items';

    public static function forPurchaseOrder(int $purchaseOrderId): array
    {
        $stmt = self::db()->prepare(
            'SELECT poi.*, p.name AS product_name, p.sku
             FROM purchase_order_items poi
             JOIN products p ON p.id = poi.product_id
             WHERE poi.purchase_order_id = :purchase_order_id
             ORDER BY poi.id'
        );
        $stmt->execute(['purchase_order_id' => $purchaseOrderId]);

        return $stmt->fetchAll();
    }
}
