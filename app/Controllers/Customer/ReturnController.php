<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\ReturnRequestStatusHistory;
use App\Models\Setting;

/**
 * Customer-facing return requests. Eligibility is gated at the whole
 * order level (orders.status = 'delivered'), not per vendor sub-order
 * - a mixed order where one vendor's slice is already delivered and
 * another isn't could in principle allow a finer-grained gate, but
 * that adds real complexity (per-item eligibility, per-item windows)
 * for a case that's the exception rather than the rule; gating on the
 * order's own delivered status keeps the customer-facing rule simple
 * and predictable ("my order is delivered, I can request a return"),
 * at the cost of a customer needing the whole order delivered even if
 * they only want to return one vendor's item.
 */
final class ReturnController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/account/returns/index', [
            'pageTitle' => 'My Returns | Kymera Collection',
            'returns' => ReturnRequest::forUser((int) Auth::id()),
        ], 'customer/layouts/site');
    }

    public function show(Request $request): void
    {
        $returnRequest = $this->ownedReturnRequest((int) $request->route('id'));

        $this->view('customer/account/returns/show', [
            'pageTitle' => 'Return Request | Kymera Collection',
            'returnRequest' => $returnRequest,
            'items' => ReturnRequestItem::forReturnRequest((int) $returnRequest['id']),
            'statusHistory' => ReturnRequestStatusHistory::forReturnRequest((int) $returnRequest['id']),
        ], 'customer/layouts/site');
    }

    public function create(Request $request): void
    {
        $order = $this->loadEligibleOrder($request);
        $eligibleItems = $this->eligibleItemsFor((int) $order['id']);

        if ($eligibleItems === []) {
            Session::flash('errors', ['order' => ['There is nothing left to return on this order.']]);
            $this->redirect('/account/orders/' . $order['order_number']);
        }

        $this->view('customer/account/returns/create', [
            'pageTitle' => 'Request a Return | Kymera Collection',
            'order' => $order,
            'eligibleItems' => $eligibleItems,
        ], 'customer/layouts/site');
    }

    public function store(Request $request): void
    {
        $order = $this->loadEligibleOrder($request);
        $eligibleItems = $this->eligibleItemsFor((int) $order['id']);
        $eligibleById = [];

        foreach ($eligibleItems as $item) {
            $eligibleById[(int) $item['id']] = $item;
        }

        $data = $this->validate($request->all(), ['reason' => 'required|max:255']);

        // Each item's quantity/reason is submitted under its own
        // uniquely-named field (quantity_{id}, item_reason_{id}), not
        // as a parallel array keyed by position - a checkbox array
        // alone (selected_item_id[]) only ever contains the entries
        // that were actually checked, so pairing it by array index
        // against a same-named quantity[] array would misalign the
        // moment any item in the middle of the list is left unchecked.
        $selectedIds = array_map('intval', (array) $request->input('selected_item_id', []));
        $toReturn = [];

        foreach ($selectedIds as $orderItemId) {
            if (!isset($eligibleById[$orderItemId])) {
                continue;
            }

            $quantity = (int) $request->input('quantity_' . $orderItemId, 0);

            if ($quantity <= 0) {
                continue;
            }

            $maxReturnable = (int) $eligibleById[$orderItemId]['returnable_quantity'];

            if ($quantity > $maxReturnable) {
                Session::flash('errors', ['items' => ['You requested more units than are available to return for one or more items.']]);
                $this->back();
            }

            $toReturn[] = [
                'order_item_id' => $orderItemId,
                'quantity' => $quantity,
                'reason' => self::nullable($request->input('item_reason_' . $orderItemId)),
            ];
        }

        if ($toReturn === []) {
            Session::flash('errors', ['items' => ['Select at least one item and quantity to return.']]);
            $this->back();
        }

        $returnRequestId = ReturnRequest::create([
            'order_id' => $order['id'],
            'user_id' => Auth::id(),
            'status' => 'pending',
            'reason' => $data['reason'],
        ]);

        foreach ($toReturn as $line) {
            ReturnRequestItem::create([
                'return_request_id' => $returnRequestId,
                'order_item_id' => $line['order_item_id'],
                'quantity' => $line['quantity'],
                'reason' => $line['reason'],
            ]);
        }

        ReturnRequestStatusHistory::create([
            'return_request_id' => $returnRequestId,
            'status' => 'pending',
            'note' => 'Return requested by customer.',
            'changed_by' => Auth::id(),
        ]);

        Session::flash('success', 'Your return request has been submitted. We\'ll review it shortly.');
        $this->redirect('/account/returns/' . $returnRequestId);
    }

    private function loadEligibleOrder(Request $request): array
    {
        $orderNumber = (string) $request->route('orderNumber');
        $order = Order::findByNumberForUser($orderNumber, (int) Auth::id());

        if ($order === null) {
            Response::abort(404, 'Order not found.');
        }

        if ($order['status'] !== 'delivered') {
            Session::flash('errors', ['order' => ['Returns can only be requested for delivered orders.']]);
            $this->redirect('/account/orders/' . $order['order_number']);
        }

        $deliveredAt = OrderStatusHistory::deliveredAt((int) $order['id']);
        $windowDays = (int) Setting::get('return_window_days', 14);

        if ($deliveredAt === null || strtotime($deliveredAt) < strtotime("-{$windowDays} days")) {
            Session::flash('errors', ['order' => ["The {$windowDays}-day return window for this order has passed."]]);
            $this->redirect('/account/orders/' . $order['order_number']);
        }

        return $order;
    }

    /**
     * Every order_item on the order, annotated with how many units are
     * still available to return (original quantity minus whatever is
     * already tied up in a non-rejected return request), filtered down
     * to only the lines that still have at least one returnable unit.
     */
    private function eligibleItemsFor(int $orderId): array
    {
        $items = OrderItem::forOrder($orderId);
        $eligible = [];

        foreach ($items as $item) {
            $alreadyRequested = ReturnRequestItem::activeQuantityForOrderItem((int) $item['id']);
            $remaining = (int) $item['quantity'] - $alreadyRequested;

            if ($remaining > 0) {
                $item['returnable_quantity'] = $remaining;
                $eligible[] = $item;
            }
        }

        return $eligible;
    }

    private function ownedReturnRequest(int $id): array
    {
        $returnRequest = ReturnRequest::findWithDetails($id);

        if ($returnRequest === null || (int) $returnRequest['user_id'] !== (int) Auth::id()) {
            Response::abort(404, 'Return request not found.');
        }

        return $returnRequest;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
