<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Storefront display currencies. `exchange_rate` is units of this
 * currency per 1 USD, manually maintained (no live FX API call) - see
 * docs/MODULE_27_MULTI_CURRENCY_REGIONAL_TAX.md for why that's a
 * deliberate scope decision, not an oversight. Every order, payment,
 * refund, and vendor payout in this app is still placed and recorded
 * in USD; this table only ever affects what a shopper sees while
 * browsing, via the displayPrice() helper.
 */
final class Currency extends Model
{
    protected static string $table = 'currencies';
    protected static string $primaryKey = 'code';

    public static function active(): array
    {
        $stmt = self::db()->query('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code ASC');

        return $stmt->fetchAll();
    }

    public static function defaultCurrency(): array
    {
        $stmt = self::db()->query('SELECT * FROM currencies WHERE is_default = 1 AND is_active = 1 LIMIT 1');
        $row = $stmt->fetch();

        return $row !== false ? $row : ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => '1.000000'];
    }
}
