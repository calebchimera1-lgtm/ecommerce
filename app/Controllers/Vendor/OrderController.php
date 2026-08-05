<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorOrderStatusHistory;

/**
 * A vendor's own order fulfillment queue - one row per vendor_order,
 * each an independent slice of a customer order that belongs to this
 * vendor and no one else. Module 15's "each vendor ships their own
 * items" decision means a vendor here only ever sees their own items
 * and the one shipping address needed to send them, never another
 * vendor's items or the order's payment/billing details.
 */
final class OrderController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request): void
    {
        $vendor = self::currentVendor();
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['status' => (string) $request->query('status', '')];

        $this->view('vendor/orders/index', [
            'pageTitle' => 'My Orders | Kymera Collection Vendor Portal',
            'vendorOrders' => VendorOrder::paginateForVendor((int) $vendor['id'], $page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => VendorOrder::countForVendor((int) $vendor['id'], $filters),
        ], 'vendor/layouts/app');
    }

    public function show(Request $request): void
    {
        $vendor = self::currentVendor();
        $vendorOrder = self::loadOwnVendorOrder($request, $vendor);

        $this->view('vendor/orders/show', [
            'pageTitle' => 'Order ' . $vendorOrder['order_number'] . ' | Kymera Collection Vendor Portal',
            'vendorOrder' => $vendorOrder,
            'items' => OrderItem::forVendorOrder((int) $vendorOrder['id']),
            'shippingAddress' => OrderAddress::forOrder((int) $vendorOrder['order_id'])['shipping'] ?? null,
            'statusHistory' => VendorOrderStatusHistory::forVendorOrder((int) $vendorOrder['id']),
            'statuses' => self::STATUSES,
        ], 'vendor/layouts/app');
    }

    public function updateStatus(Request $request): void
    {
        $vendor = self::currentVendor();
        $vendorOrder = self::loadOwnVendorOrder($request, $vendor);
        $status = (string) $request->input('status');

        if (!in_array($status, self::STATUSES, true)) {
            Session::flash('errors', ['status' => ['Invalid status.']]);
            $this->back();
        }

        VendorOrder::updateStatus((int) $vendorOrder['id'], $status, self::nullable($request->input('note')), (int) Auth::id());

        Session::flash('success', 'Order status updated.');
        $this->redirect('/vendor/orders/' . (int) $vendorOrder['id']);
    }

    private static function currentVendor(): array
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        if ($vendor === null) {
            Response::abort(403, 'No vendor profile is linked to this account.');
        }

        return $vendor;
    }

    private static function loadOwnVendorOrder(Request $request, array $vendor): array
    {
        $vendorOrder = VendorOrder::findForVendor((int) $request->route('id'), (int) $vendor['id']);

        if ($vendorOrder === null) {
            Response::abort(404, 'Order not found.');
        }

        return $vendorOrder;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
