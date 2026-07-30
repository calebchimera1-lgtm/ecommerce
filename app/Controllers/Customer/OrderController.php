<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingMethod;

final class OrderController extends Controller
{
    public function confirmation(Request $request): void
    {
        $order = $this->loadOwnedOrder($request);

        $this->view('customer/order/confirmation', [
            'pageTitle' => 'Order Confirmed | Kymera Collection',
            'order' => $order,
            'items' => OrderItem::forOrder((int) $order['id']),
            'addresses' => OrderAddress::forOrder((int) $order['id']),
            'payment' => Payment::forOrder((int) $order['id']),
            'shippingMethod' => $order['shipping_method_id'] !== null ? ShippingMethod::find((int) $order['shipping_method_id']) : null,
        ], 'customer/layouts/site');
    }

    public function invoice(Request $request): void
    {
        $order = $this->loadOwnedOrder($request);

        $this->view('customer/order/invoice', [
            'pageTitle' => 'Invoice ' . $order['order_number'] . ' | Kymera Collection',
            'order' => $order,
            'items' => OrderItem::forOrder((int) $order['id']),
            'addresses' => OrderAddress::forOrder((int) $order['id']),
            'payment' => Payment::forOrder((int) $order['id']),
        ], 'customer/layouts/invoice');
    }

    private function loadOwnedOrder(Request $request): array
    {
        $orderNumber = (string) $request->route('orderNumber');
        $order = Order::findByNumberForUser($orderNumber, (int) Auth::id());

        if ($order === null) {
            Response::abort(404, 'Order not found.');
        }

        return $order;
    }
}
