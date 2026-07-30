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

    /**
     * Called once checkout actually knows the shipping country, so the
     * final order uses a jurisdiction-matched rate instead of the
     * cart page's placeholder estimate. Falls back to the default rate
     * if no rate is configured for that country.
     */
    public static function forCountry(string $country): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM tax_rates WHERE is_active = 1 AND country = :country ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['country' => $country]);
        $row = $stmt->fetch();

        return $row !== false ? $row : self::defaultRate();
    }
}
