<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shipment;

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
        ], 'admin/layouts/app');
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
