<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ShippingMethod extends Model
{
    protected static string $table = 'shipping_methods';

    public static function activeOrdered(): array
    {
        $stmt = self::db()->query('SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY cost ASC');

        return $stmt->fetchAll();
    }
}
