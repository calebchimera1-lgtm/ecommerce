<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * Read-only earnings ledger - what a vendor is owed and what they've
 * already been paid. Distinct from My Orders (fulfillment work list)
 * even though both read from vendor_orders: this page answers "how
 * much money", that one answers "what do I need to ship". Payouts
 * themselves are still manual per Module 15's decision - a vendor
 * can see their balance here but the app never moves money; an admin
 * records each payout from the admin side after paying the vendor
 * outside the app.
 */
final class PayoutController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        if ($vendor === null) {
            Response::abort(403, 'No vendor profile is linked to this account.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $filters = ['payout_status' => (string) $request->query('payout_status', '')];

        $this->view('vendor/payouts/index', [
            'pageTitle' => 'Payouts | Kymera Collection Vendor Portal',
            'vendorOrders' => VendorOrder::paginateForVendor((int) $vendor['id'], $page, self::PER_PAGE, $filters),
            'unpaidBalance' => VendorOrder::unpaidBalanceForVendor((int) $vendor['id']),
            'paidToDate' => VendorOrder::paidToDateForVendor((int) $vendor['id']),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => VendorOrder::countForVendor((int) $vendor['id'], $filters),
        ], 'vendor/layouts/app');
    }
}
