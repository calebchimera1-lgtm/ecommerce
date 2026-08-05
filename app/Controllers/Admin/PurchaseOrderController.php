<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;

final class PurchaseOrderController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'status' => (string) $request->query('status', ''),
            'supplier_id' => (string) $request->query('supplier_id', ''),
        ];

        $this->view('admin/purchase-orders/index', [
            'pageTitle' => 'Purchase Orders | Kymera Collection Admin',
            'purchaseOrders' => PurchaseOrder::paginateAll($page, self::PER_PAGE, $filters),
            'suppliers' => Supplier::active(),
            'statuses' => self::STATUSES,
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => PurchaseOrder::countAll($filters),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/purchase-orders/create', [
            'pageTitle' => 'New Purchase Order | Kymera Collection Admin',
            'suppliers' => Supplier::active(),
            'products' => Product::forSelect(),
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'supplier_id' => 'required|integer',
        ]);

        if (Supplier::find((int) $data['supplier_id']) === null) {
            Session::flash('errors', ['supplier_id' => ['Selected supplier does not exist.']]);
            $this->back();
        }

        $items = self::buildItems($request);

        if ($items === []) {
            Session::flash('errors', ['items' => ['Add at least one line item with a product, quantity, and unit cost.']]);
            $this->back();
        }

        $expectedAt = trim((string) $request->input('expected_at', ''));

        $poId = PurchaseOrder::createWithItems(
            (int) $data['supplier_id'],
            $expectedAt !== '' ? $expectedAt : null,
            Auth::id(),
            $items
        );

        AuditLog::record(Auth::id(), 'purchase_order.created', 'purchase_order', $poId, null, [
            'supplier_id' => (int) $data['supplier_id'],
            'item_count' => count($items),
        ]);

        Session::flash('success', 'Purchase order created as a draft.');
        $this->redirect('/admin/purchase-orders/' . $poId);
    }

    public function show(Request $request): void
    {
        $po = $this->loadPurchaseOrder($request);

        $this->view('admin/purchase-orders/show', [
            'pageTitle' => 'Purchase Order #' . $po['id'] . ' | Kymera Collection Admin',
            'po' => $po,
            'items' => PurchaseOrderItem::forPurchaseOrder((int) $po['id']),
        ], 'admin/layouts/app');
    }

    public function markOrdered(Request $request): void
    {
        $po = $this->loadPurchaseOrder($request);

        if ($po['status'] !== 'draft') {
            Session::flash('errors', ['status' => ['Only draft purchase orders can be marked as ordered.']]);
            $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
        }

        PurchaseOrder::markOrdered((int) $po['id']);

        AuditLog::record(Auth::id(), 'purchase_order.ordered', 'purchase_order', (int) $po['id'], ['status' => 'draft'], ['status' => 'ordered']);

        Session::flash('success', 'Purchase order marked as ordered.');
        $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
    }

    public function receive(Request $request): void
    {
        $po = $this->loadPurchaseOrder($request);

        if (!in_array($po['status'], ['ordered', 'partially_received'], true)) {
            Session::flash('errors', ['status' => ['Only ordered purchase orders can receive stock.']]);
            $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
        }

        $quantities = [];

        foreach ((array) $request->input('receive_qty', []) as $itemId => $qty) {
            $quantities[(int) $itemId] = (int) $qty;
        }

        $applied = PurchaseOrder::receiveItems((int) $po['id'], $quantities, Auth::id());

        AuditLog::record(Auth::id(), 'purchase_order.received', 'purchase_order', (int) $po['id'], null, [
            'received' => $applied,
        ]);

        Session::flash('success', 'Stock received and inventory updated.');
        $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
    }

    public function cancel(Request $request): void
    {
        $po = $this->loadPurchaseOrder($request);

        if (!in_array($po['status'], ['draft', 'ordered'], true)) {
            Session::flash('errors', ['status' => ['Only draft or ordered purchase orders can be cancelled - stock has already been received against this one.']]);
            $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
        }

        PurchaseOrder::cancel((int) $po['id']);

        AuditLog::record(Auth::id(), 'purchase_order.cancelled', 'purchase_order', (int) $po['id'], ['status' => $po['status']], ['status' => 'cancelled']);

        Session::flash('success', 'Purchase order cancelled.');
        $this->redirect('/admin/purchase-orders/' . (int) $po['id']);
    }

    private function loadPurchaseOrder(Request $request): array
    {
        $id = (int) $request->route('id');
        $po = PurchaseOrder::findWithSupplier($id);

        if ($po === null) {
            Response::abort(404, 'Purchase order not found.');
        }

        return $po;
    }

    private static function buildItems(Request $request): array
    {
        $productIds = (array) $request->input('product_id', []);
        $quantities = (array) $request->input('quantity', []);
        $unitCosts = (array) $request->input('unit_cost', []);
        $items = [];

        foreach ($productIds as $i => $productId) {
            $productId = (int) $productId;
            $quantity = (int) ($quantities[$i] ?? 0);
            $unitCost = trim((string) ($unitCosts[$i] ?? ''));

            if ($productId <= 0 || $quantity <= 0 || $unitCost === '' || Product::find($productId) === null) {
                continue;
            }

            $items[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_cost' => $unitCost];
        }

        return $items;
    }
}
