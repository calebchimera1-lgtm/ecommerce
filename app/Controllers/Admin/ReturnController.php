<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\ReturnRequestStatusHistory;
use App\Models\VendorOrder;
use Throwable;

/**
 * Admin side of the RMA workflow: approve/reject a pending request,
 * then separately mark an approved one refunded (a distinct step,
 * since "we agreed to take this back" and "the money has actually
 * moved" happen at different times in a real store, and only the
 * second step touches stock and vendor payout figures).
 */
final class ReturnController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['status' => (string) $request->query('status', '')];

        $this->view('admin/returns/index', [
            'pageTitle' => 'Returns | Kymera Collection Admin',
            'returns' => ReturnRequest::paginateAdmin($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => ReturnRequest::countAdmin($filters),
        ], 'admin/layouts/app');
    }

    public function show(Request $request): void
    {
        $returnRequest = $this->loadReturnRequest($request);
        $items = ReturnRequestItem::forReturnRequest((int) $returnRequest['id']);
        $suggestedRefund = array_reduce(
            $items,
            static fn (float $carry, array $item): float => $carry + (float) $item['price'] * (int) $item['quantity'],
            0.0
        );

        $this->view('admin/returns/show', [
            'pageTitle' => 'Return Request | Kymera Collection Admin',
            'returnRequest' => $returnRequest,
            'items' => $items,
            'statusHistory' => ReturnRequestStatusHistory::forReturnRequest((int) $returnRequest['id']),
            'suggestedRefund' => $suggestedRefund,
        ], 'admin/layouts/app');
    }

    public function approve(Request $request): void
    {
        $returnRequest = $this->loadReturnRequest($request);

        if ($returnRequest['status'] !== 'pending') {
            Session::flash('errors', ['status' => ['Only pending requests can be approved.']]);
            $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
        }

        ReturnRequest::approve((int) $returnRequest['id'], (int) Auth::id(), self::nullable($request->input('admin_note')));
        AuditLog::record(Auth::id(), 'return_request.approved', 'return_request', (int) $returnRequest['id'], ['status' => 'pending'], ['status' => 'approved']);

        Session::flash('success', 'Return request approved.');
        $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
    }

    public function reject(Request $request): void
    {
        $returnRequest = $this->loadReturnRequest($request);

        if ($returnRequest['status'] !== 'pending') {
            Session::flash('errors', ['status' => ['Only pending requests can be rejected.']]);
            $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
        }

        $data = $this->validate($request->all(), ['admin_note' => 'required|max:255']);

        ReturnRequest::reject((int) $returnRequest['id'], (int) Auth::id(), $data['admin_note']);
        AuditLog::record(Auth::id(), 'return_request.rejected', 'return_request', (int) $returnRequest['id'], ['status' => 'pending'], ['status' => 'rejected', 'reason' => $data['admin_note']]);

        Session::flash('success', 'Return request rejected.');
        $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
    }

    /**
     * Marks an approved return as refunded: records the actual amount
     * refunded (pre-filled with the line total, editable - a
     * restocking fee or partial refund is a real business decision,
     * not something to compute rigidly), restocks whichever lines the
     * admin checked, and adjusts the affected vendor(s)' unpaid payout
     * figures downward. Deliberately does NOT touch a vendor_orders
     * row whose payout_status is already 'paid' - that money already
     * left the ledger as paid, so silently rewriting a paid record
     * would misrepresent what actually happened; the admin sees an
     * explicit warning instead and adjusts that vendor's real-world
     * payout manually, consistent with Module 15's manual-payout
     * design.
     */
    public function refund(Request $request): void
    {
        $returnRequest = $this->loadReturnRequest($request);

        if ($returnRequest['status'] !== 'approved') {
            Session::flash('errors', ['status' => ['Only approved requests can be marked refunded.']]);
            $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
        }

        $data = $this->validate($request->all(), ['refunded_amount' => 'required|numeric']);
        $restockItemIds = array_map('intval', (array) $request->input('restock', []));
        $items = ReturnRequestItem::forReturnRequest((int) $returnRequest['id']);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $vendorAlreadyPaidWarning = false;

            foreach ($items as $item) {
                if (in_array((int) $item['id'], $restockItemIds, true)) {
                    Product::adjustStock((int) $item['product_id'], (int) $item['quantity']);
                    InventoryMovement::create([
                        'product_id' => $item['product_id'],
                        'type' => 'return',
                        'quantity' => (int) $item['quantity'],
                        'reference_type' => 'return_request',
                        'reference_id' => $returnRequest['id'],
                        'note' => 'Restocked from return request',
                        'created_by' => Auth::id(),
                    ]);
                    ReturnRequestItem::markRestocked((int) $item['id']);
                }

                if ($item['vendor_order_id'] !== null) {
                    $adjusted = self::deductFromVendorOrder((int) $item['vendor_order_id'], $item);

                    if (!$adjusted) {
                        $vendorAlreadyPaidWarning = true;
                    }
                }
            }

            ReturnRequest::markRefunded((int) $returnRequest['id'], (int) Auth::id(), (string) $data['refunded_amount']);
            self::syncOrderPaymentStatus((int) $returnRequest['order_id']);

            AuditLog::record(Auth::id(), 'return_request.refunded', 'return_request', (int) $returnRequest['id'], [
                'status' => 'approved',
            ], [
                'status' => 'refunded',
                'refunded_amount' => $data['refunded_amount'],
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Session::flash('success', $vendorAlreadyPaidWarning
            ? 'Return marked as refunded. One or more affected vendors were already paid for this item - adjust their next manual payout accordingly.'
            : 'Return marked as refunded.');
        $this->redirect('/admin/returns/' . (int) $returnRequest['id']);
    }

    /**
     * @return bool true if the vendor_orders row was adjusted (payout was still unpaid), false if it was already paid and left untouched.
     */
    private static function deductFromVendorOrder(int $vendorOrderId, array $returnItem): bool
    {
        $vendorOrder = VendorOrder::find($vendorOrderId);

        if ($vendorOrder === null || $vendorOrder['payout_status'] !== 'unpaid') {
            return false;
        }

        $returnedSubtotal = (float) $returnItem['price'] * (int) $returnItem['quantity'];
        $commissionRate = $returnItem['commission_rate'] !== null ? (float) $returnItem['commission_rate'] : 0.0;
        $returnedCommission = round($returnedSubtotal * $commissionRate / 100, 2);
        $returnedPayout = round($returnedSubtotal - $returnedCommission, 2);

        VendorOrder::update($vendorOrderId, [
            'subtotal' => (string) max(0.0, (float) $vendorOrder['subtotal'] - $returnedSubtotal),
            'commission_amount' => (string) max(0.0, (float) $vendorOrder['commission_amount'] - $returnedCommission),
            'payout_amount' => (string) max(0.0, (float) $vendorOrder['payout_amount'] - $returnedPayout),
        ]);

        return true;
    }

    /**
     * Flips the parent order's payment_status to 'refunded' once the
     * sum of every refunded return on it covers the full order total -
     * a partial refund (the common case) leaves payment_status alone,
     * since the schema's payment_status is a single enum, not a
     * running balance, and 'refunded' should mean "nothing more is
     * owed on this order", not "something was refunded".
     */
    private static function syncOrderPaymentStatus(int $orderId): void
    {
        $order = Order::find($orderId);

        if ($order === null || $order['payment_status'] === 'refunded') {
            return;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(refunded_amount), 0) AS total FROM return_requests
             WHERE order_id = :order_id AND status = 'refunded'"
        );
        $stmt->execute(['order_id' => $orderId]);
        $totalRefunded = (float) $stmt->fetch()['total'];

        if ($totalRefunded >= (float) $order['total']) {
            Order::update($orderId, ['payment_status' => 'refunded']);
        }
    }

    private function loadReturnRequest(Request $request): array
    {
        $id = (int) $request->route('id');
        $returnRequest = ReturnRequest::findWithDetails($id);

        if ($returnRequest === null) {
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
