<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\VendorOrder;

/**
 * Minimal order management: list, view, change status, and attach
 * shipment/tracking info. Full admin order tooling (refunds, exports,
 * bulk actions) is a later module - this exists because Module 8's
 * customer-facing order tracking page needs real admin-entered status
 * and shipment data to be testable at all.
 */
final class OrderController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    private const VENDOR_ORDER_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['status' => (string) $request->query('status', '')];

        $this->view('admin/orders/index', [
            'pageTitle' => 'Orders | Kymera Collection Admin',
            'orders' => Order::paginateAll($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Order::countAll($filters),
        ], 'admin/layouts/app');
    }

    public function show(Request $request): void
    {
        $order = $this->loadOrder($request);

        $this->view('admin/orders/show', [
            'pageTitle' => 'Order ' . $order['order_number'] . ' | Kymera Collection Admin',
            'order' => $order,
            'items' => OrderItem::forOrder((int) $order['id']),
            'addresses' => OrderAddress::forOrder((int) $order['id']),
            'payment' => Payment::forOrder((int) $order['id']),
            'statusHistory' => OrderStatusHistory::forOrder((int) $order['id']),
            'shipment' => Shipment::forOrder((int) $order['id']),
            'statuses' => self::STATUSES,
            'vendorOrders' => VendorOrder::forOrder((int) $order['id']),
            'vendorOrderStatuses' => self::VENDOR_ORDER_STATUSES,
        ], 'admin/layouts/app');
    }

    /**
     * Lets admin override a vendor's fulfillment status on their own
     * slice of this order - staff oversight for when a vendor forgets
     * to update it themselves. Vendors update the same field from
     * their own portal (Vendor\OrderController::updateStatus());
     * either path writes the same vendor_order_status_history trail.
     */
    public function updateVendorOrderStatus(Request $request): void
    {
        $order = $this->loadOrder($request);
        $vendorOrderId = (int) $request->route('vendorOrderId');
        $status = (string) $request->input('status');

        if (!in_array($status, self::VENDOR_ORDER_STATUSES, true)) {
            Session::flash('errors', ['status' => ['Invalid status.']]);
            $this->back();
        }

        $belongsToOrder = array_filter(
            VendorOrder::forOrder((int) $order['id']),
            static fn (array $vo): bool => (int) $vo['id'] === $vendorOrderId
        );

        if ($belongsToOrder === []) {
            Response::abort(404, 'Vendor order not found on this order.');
        }

        VendorOrder::updateStatus($vendorOrderId, $status, self::nullable($request->input('note')), (int) Auth::id());

        Session::flash('success', 'Vendor order status updated.');
        $this->redirect('/admin/orders/' . (int) $order['id']);
    }

    public function updateStatus(Request $request): void
    {
        $order = $this->loadOrder($request);
        $status = (string) $request->input('status');

        if (!in_array($status, self::STATUSES, true)) {
            Session::flash('errors', ['status' => ['Invalid status.']]);
            $this->back();
        }

        Order::updateStatus((int) $order['id'], $status, self::nullable($request->input('note')), (int) Auth::id());

        Session::flash('success', 'Order status updated.');
        $this->redirect('/admin/orders/' . (int) $order['id']);
    }

    public function updateShipment(Request $request): void
    {
        $order = $this->loadOrder($request);

        $data = $this->validate($request->all(), [
            'shipment_status' => 'required|in:pending,in_transit,out_for_delivery,delivered,failed',
        ]);

        $shippedAt = $request->input('shipped_at');
        $deliveredAt = $request->input('delivered_at');

        Shipment::createOrUpdate((int) $order['id'], [
            'courier' => self::nullable($request->input('courier')),
            'tracking_number' => self::nullable($request->input('tracking_number')),
            'status' => $data['shipment_status'],
            'estimated_delivery' => self::nullable($request->input('estimated_delivery')),
            'shipped_at' => self::nullable($shippedAt) !== null ? str_replace('T', ' ', $shippedAt) : null,
            'delivered_at' => self::nullable($deliveredAt) !== null ? str_replace('T', ' ', $deliveredAt) : null,
        ]);

        Session::flash('success', 'Shipment details updated.');
        $this->redirect('/admin/orders/' . (int) $order['id']);
    }

    public function markPaid(Request $request): void
    {
        $order = $this->loadOrder($request);

        if ($order['payment_status'] !== 'paid') {
            Order::markPaid((int) $order['id']);
            AuditLog::record(Auth::id(), 'order.payment_marked_paid', 'order', (int) $order['id'], [
                'payment_status' => $order['payment_status'],
            ], ['payment_status' => 'paid']);
        }

        Session::flash('success', 'Order marked as paid.');
        $this->redirect('/admin/orders/' . (int) $order['id']);
    }

    private function loadOrder(Request $request): array
    {
        $id = (int) $request->route('id');
        $order = Order::find($id);

        if ($order === null) {
            Response::abort(404, 'Order not found.');
        }

        return $order;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
