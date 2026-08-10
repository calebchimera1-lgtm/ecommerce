<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ReturnRequestItem extends Model
{
    protected static string $table = 'return_request_items';

    public static function forReturnRequest(int $returnRequestId): array
    {
        $stmt = self::db()->prepare(
            'SELECT rri.*, oi.product_name, oi.sku, oi.price, oi.product_id, oi.vendor_order_id, oi.commission_rate
             FROM return_request_items rri
             JOIN order_items oi ON oi.id = rri.order_item_id
             WHERE rri.return_request_id = :return_request_id
             ORDER BY rri.id ASC'
        );
        $stmt->execute(['return_request_id' => $returnRequestId]);

        return $stmt->fetchAll();
    }

    /**
     * How many units of one order_item are already tied up in a
     * non-rejected return request (pending, approved, or refunded) -
     * a rejected request's quantity doesn't count against the item,
     * since the customer is free to request again. Used to stop a
     * customer requesting more units back than they have left to
     * return, whether in one request or across several.
     */
    public static function activeQuantityForOrderItem(int $orderItemId): int
    {
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(rri.quantity), 0) AS total
             FROM return_request_items rri
             JOIN return_requests rr ON rr.id = rri.return_request_id
             WHERE rri.order_item_id = :order_item_id AND rr.status != 'rejected'"
        );
        $stmt->execute(['order_item_id' => $orderItemId]);

        return (int) $stmt->fetch()['total'];
    }

    public static function markRestocked(int $id): void
    {
        self::update($id, ['restocked' => 1]);
    }
}
