<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;

final class InventoryController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Adjustment types that add to stock vs. subtract from it. Only
     * 'adjustment' lets the admin type a signed delta directly (a
     * stock-take correction can go either way); the others force the
     * sign so "damaged" can never accidentally increase stock.
     */
    private const INCREASING_TYPES = ['in', 'return'];
    private const DECREASING_TYPES = ['out', 'damaged'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'stock' => (string) $request->query('stock', ''),
        ];

        $this->view('admin/inventory/index', [
            'pageTitle' => 'Inventory | Kymera Collection Admin',
            'products' => Product::paginateInventory($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Product::countInventory($filters),
        ], 'admin/layouts/app');
    }

    public function adjust(Request $request): void
    {
        $id = (int) $request->route('id');
        $product = Product::find($id);

        if ($product === null || $product['deleted_at'] !== null) {
            Response::abort(404, 'Product not found.');
        }

        $data = $this->validate($request->all(), [
            'type' => 'required|in:in,out,adjustment,damaged,return',
            'quantity' => 'required|integer',
        ]);

        $quantity = (int) $data['quantity'];
        $type = $data['type'];

        $delta = match (true) {
            in_array($type, self::INCREASING_TYPES, true) => abs($quantity),
            in_array($type, self::DECREASING_TYPES, true) => -abs($quantity),
            default => $quantity,
        };

        if ($delta === 0) {
            Session::flash('errors', ['quantity' => ['Enter a non-zero quantity.']]);
            $this->back();
        }

        Product::adjustStock($id, $delta);

        InventoryMovement::create([
            'product_id' => $id,
            'type' => $type,
            'quantity' => $delta,
            'reference_type' => 'manual',
            'reference_id' => null,
            'note' => self::nullable($request->input('note')),
            'created_by' => Auth::id(),
        ]);

        AuditLog::record(Auth::id(), 'inventory.adjusted', 'product', $id, [
            'stock_quantity' => (int) $product['stock_quantity'],
        ], [
            'stock_quantity' => (int) $product['stock_quantity'] + $delta,
            'delta' => $delta,
            'type' => $type,
        ]);

        Session::flash('success', 'Stock adjusted.');
        $this->redirect('/admin/inventory');
    }

    public function movements(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['product_id' => (string) $request->query('product_id', '')];

        $this->view('admin/inventory/movements', [
            'pageTitle' => 'Inventory Movements | Kymera Collection Admin',
            'movements' => InventoryMovement::paginateAll($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => InventoryMovement::countAll($filters),
        ], 'admin/layouts/app');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
