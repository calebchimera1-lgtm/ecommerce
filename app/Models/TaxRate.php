<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TaxRate extends Model
{
    protected static string $table = 'tax_rates';

    /**
     * The cart page can't yet know the customer's shipping
     * jurisdiction (address collection is Module 7's checkout), so it
     * shows an estimated tax using the store's first active rate,
     * clearly labeled as an estimate. Final tax is computed once an
     * address exists.
     */
    public static function defaultRate(): ?array
    {
        $stmt = self::db()->query('SELECT * FROM tax_rates WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
