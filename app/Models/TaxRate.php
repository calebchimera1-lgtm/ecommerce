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
     * Called once checkout actually knows the shipping address, so the
     * final order uses a jurisdiction-matched rate instead of the
     * cart page's placeholder estimate. A state-specific row (e.g. US
     * state sales tax, which varies far more than country-level VAT)
     * is preferred over the country-wide rate when both the country
     * and the free-text $state match; otherwise falls back to the
     * country-level rate (state IS NULL), then the global default if
     * the country itself has no configured rate at all. $state is
     * matched case-/whitespace-insensitively since checkout collects
     * it as free text, not a fixed list of codes - a known limitation
     * (no "CA" vs "California" normalization) documented in
     * docs/MODULE_27_MULTI_CURRENCY_REGIONAL_TAX.md.
     */
    public static function forAddress(string $country, ?string $state = null): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM tax_rates
             WHERE is_active = 1 AND country = :country
               AND (state IS NULL OR LOWER(TRIM(state)) = LOWER(TRIM(:state)))
             ORDER BY (state IS NOT NULL) DESC, id ASC
             LIMIT 1'
        );
        $stmt->execute(['country' => $country, 'state' => $state ?? '']);
        $row = $stmt->fetch();

        return $row !== false ? $row : self::defaultRate();
    }
}
