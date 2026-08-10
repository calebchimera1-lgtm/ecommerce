<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Currency;

/**
 * A pure display preference, not a state-changing action against any
 * protected resource - same category as a language/theme switcher, so
 * this is a GET route (bookmarkable, no CSRF token needed) rather than
 * a POST, consistent with how this app only requires CSRF on routes
 * that mutate real data (see Module 24's CSRF audit).
 */
final class CurrencyController extends Controller
{
    public function set(Request $request): void
    {
        $code = strtoupper((string) $request->route('code'));
        $currency = Currency::find($code);

        if ($currency !== null && (int) $currency['is_active'] === 1) {
            Session::set('currency', $code);
        }

        $this->back();
    }
}
