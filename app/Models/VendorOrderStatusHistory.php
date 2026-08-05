<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class VendorOrderStatusHistory extends Model
{
    protected static string $table = 'vendor_order_status_history';

    public static function forVendorOrder(int $vendorOrderId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM vendor_order_status_history WHERE vendor_order_id = :vendor_order_id ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['vendor_order_id' => $vendorOrderId]);

        return $stmt->fetchAll();
    }
}
