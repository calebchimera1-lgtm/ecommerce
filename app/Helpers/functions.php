<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Models\Currency;

/**
 * Global helper functions, autoloaded via composer.json's "files"
 * directive. Kept intentionally small - most logic belongs in
 * App\Core classes; these are thin convenience wrappers used from
 * views and controllers.
 */

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        $config ??= require dirname(__DIR__, 2) . '/config/config.php';

        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        static $values = null;
        // Session::getFlash() removes the key from the session on first
        // read; cache it locally so repeated old() calls within the same
        // request (one per form field) still see the values.
        $values ??= Session::getFlash('old', []);

        return $values[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money(float|string $amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'KES' => 'KSh '];
        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . number_format((float) $amount, 2);
    }
}

if (!function_exists('currentCurrency')) {
    /**
     * The visitor's chosen storefront display currency (session-scoped
     * - see Customer\CurrencyController), falling back to whichever
     * currency `currencies.is_default = 1` marks. Never touches
     * anything the visitor didn't explicitly pick, so admin/vendor
     * dashboards and order/invoice pages - which call money() directly
     * and never this - stay in authoritative USD regardless of what a
     * staff member last selected while browsing the storefront.
     */
    function currentCurrency(): array
    {
        static $currency = null;

        if ($currency !== null) {
            return $currency;
        }

        $code = Session::get('currency');
        $currency = $code !== null ? Currency::find((string) $code) : null;
        $currency ??= Currency::defaultCurrency();

        return $currency;
    }
}

if (!function_exists('displayPrice')) {
    /**
     * Converts a USD amount to the visitor's chosen display currency
     * for storefront browsing only (product listings/detail pages) -
     * deliberately separate from money(), which every authoritative
     * money figure in this app (cart/checkout totals, order history,
     * invoices, admin/vendor dashboards, RMA refunds, payouts) keeps
     * calling directly in USD. Conversion uses `currencies.exchange_rate`,
     * a manually-maintained rate, not a live FX lookup.
     */
    function displayPrice(float|string $usdAmount): string
    {
        $currency = currentCurrency();
        $converted = (float) $usdAmount * (float) $currency['exchange_rate'];

        return $currency['symbol'] . number_format($converted, 2);
    }
}
