<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * Manual payout ledger - Module 15's decision that vendors get paid
 * outside the app, tracked here rather than automated. This exists
 * for staff to answer two questions: "who is owed money right now"
 * (index(), every vendor's balance) and "for this one vendor, which
 * specific orders make up that balance, and which have already been
 * paid" (vendor(), with the mark-paid action per order).
 *
 * Deliberately its own controller rather than folded into
 * Admin\VendorController - approving/suspending a vendor account
 * (Module 16) and recording that real money changed hands are
 * different concerns that happen to both be about a vendor, the same
 * reasoning that already keeps Admin\ProductController separate from
 * Admin\OrderController despite both touching products.
 */
final class PayoutController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $this->view('admin/payouts/index', [
            'pageTitle' => 'Vendor Payouts | Kymera Collection Admin',
            'balances' => VendorOrder::balancesByVendor(),
        ], 'admin/layouts/app');
    }

    public function vendor(Request $request): void
    {
        $vendor = self::loadVendor($request);
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['payout_status' => (string) $request->query('payout_status', '')];

        $this->view('admin/payouts/vendor', [
            'pageTitle' => $vendor['store_name'] . ' Payouts | Kymera Collection Admin',
            'vendor' => $vendor,
            'vendorOrders' => VendorOrder::paginateForVendor((int) $vendor['id'], $page, self::PER_PAGE, $filters),
            'unpaidBalance' => VendorOrder::unpaidBalanceForVendor((int) $vendor['id']),
            'paidToDate' => VendorOrder::paidToDateForVendor((int) $vendor['id']),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => VendorOrder::countForVendor((int) $vendor['id'], $filters),
        ], 'admin/layouts/app');
    }

    public function markPaid(Request $request): void
    {
        $vendor = self::loadVendor($request);
        $vendorOrderId = (int) $request->route('vendorOrderId');
        $vendorOrder = VendorOrder::findForVendor($vendorOrderId, (int) $vendor['id']);

        if ($vendorOrder === null) {
            Response::abort(404, 'Payout not found for this vendor.');
        }

        if ($vendorOrder['payout_status'] === 'paid') {
            Session::flash('errors', ['payout_status' => ['This payout is already marked as paid.']]);
            $this->redirect('/admin/payouts/' . (int) $vendor['id']);
        }

        $reference = trim((string) $request->input('payout_reference', ''));
        VendorOrder::markPaid($vendorOrderId, (int) Auth::id(), $reference !== '' ? $reference : null);

        AuditLog::record(Auth::id(), 'vendor_order.paid', 'vendor_order', $vendorOrderId, [
            'payout_status' => 'unpaid',
        ], [
            'payout_status' => 'paid',
            'payout_amount' => $vendorOrder['payout_amount'],
            'reference' => $reference !== '' ? $reference : null,
        ]);

        Session::flash('success', 'Payout marked as paid.');
        $this->redirect('/admin/payouts/' . (int) $vendor['id']);
    }

    private static function loadVendor(Request $request): array
    {
        $id = (int) $request->route('id');
        $vendor = Vendor::find($id);

        if ($vendor === null) {
            Response::abort(404, 'Vendor not found.');
        }

        return $vendor;
    }
}
