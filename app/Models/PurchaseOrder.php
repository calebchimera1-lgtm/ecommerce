<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class PurchaseOrder extends Model
{
    protected static string $table = 'purchase_orders';

    /**
     * @param array{status?:string,supplier_id?:string|int} $filters
     */
    public static function paginateAll(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT po.*, s.name AS supplier_name
             FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             {$where}
             ORDER BY po.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countAll(array $filters = []): int
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM purchase_orders po {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'po.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['supplier_id'] ?? '') !== '') {
            $conditions[] = 'po.supplier_id = :supplier_id';
            $bindings['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    public static function findWithSupplier(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT po.*, s.name AS supplier_name, s.contact_person, s.email AS supplier_email, s.phone AS supplier_phone
             FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Creates a draft PO with its line items in one transaction.
     * $items is a list of ['product_id'=>int,'quantity'=>int,'unit_cost'=>string].
     */
    public static function createWithItems(int $supplierId, ?string $expectedAt, ?int $createdBy, array $items): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $total = 0.0;

            foreach ($items as $item) {
                $total += (int) $item['quantity'] * (float) $item['unit_cost'];
            }

            $poId = self::create([
                'supplier_id' => $supplierId,
                'status' => 'draft',
                'total_amount' => number_format($total, 2, '.', ''),
                'expected_at' => $expectedAt,
                'created_by' => $createdBy,
            ]);

            $insert = $db->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_cost)
                 VALUES (:purchase_order_id, :product_id, :quantity, :unit_cost)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'purchase_order_id' => $poId,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (string) $item['unit_cost'],
                ]);
            }

            $db->commit();

            return $poId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function markOrdered(int $id): void
    {
        self::update($id, ['status' => 'ordered', 'ordered_at' => date('Y-m-d H:i:s')]);
    }

    public static function cancel(int $id): void
    {
        self::update($id, ['status' => 'cancelled']);
    }

    /**
     * Receives stock against a PO. $quantities is [item_id => quantity
     * to receive now]. Each item's received_quantity is capped at its
     * ordered quantity, the delta actually applied is added to the
     * product's stock with a matching inventory_movements row, and the
     * PO's own status is recomputed from the items afterward -
     * 'received' once every line is fully in, 'partially_received' if
     * some but not all stock has arrived. All in one transaction so a
     * failure partway through never leaves stock and PO status out of
     * sync with each other.
     */
    /**
     * @return array<int,int> item id => quantity actually applied (may be
     * less than requested if the request exceeded what was still owed)
     */
    public static function receiveItems(int $poId, array $quantities, ?int $receivedBy): array
    {
        $db = self::db();
        $db->beginTransaction();
        $applied = [];

        try {
            $items = PurchaseOrderItem::forPurchaseOrder($poId);

            foreach ($items as $item) {
                $requested = (int) ($quantities[$item['id']] ?? 0);

                if ($requested <= 0) {
                    continue;
                }

                $remaining = (int) $item['quantity'] - (int) $item['received_quantity'];
                $delta = min($requested, $remaining);

                if ($delta <= 0) {
                    continue;
                }

                $newReceived = (int) $item['received_quantity'] + $delta;

                $stmt = $db->prepare('UPDATE purchase_order_items SET received_quantity = :received_quantity WHERE id = :id');
                $stmt->execute(['received_quantity' => $newReceived, 'id' => $item['id']]);

                Product::adjustStock((int) $item['product_id'], $delta);

                InventoryMovement::create([
                    'product_id' => $item['product_id'],
                    'type' => 'in',
                    'quantity' => $delta,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $poId,
                    'note' => 'Received against PO #' . $poId,
                    'created_by' => $receivedBy,
                ]);

                $applied[(int) $item['id']] = $delta;
            }

            self::recomputeStatus($poId);

            $db->commit();

            return $applied;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function recomputeStatus(int $poId): void
    {
        $items = PurchaseOrderItem::forPurchaseOrder($poId);

        $totalOrdered = 0;
        $totalReceived = 0;

        foreach ($items as $item) {
            $totalOrdered += (int) $item['quantity'];
            $totalReceived += (int) $item['received_quantity'];
        }

        if ($totalReceived <= 0) {
            return;
        }

        if ($totalReceived >= $totalOrdered) {
            self::update($poId, ['status' => 'received', 'received_at' => date('Y-m-d H:i:s')]);
        } else {
            self::update($poId, ['status' => 'partially_received']);
        }
    }
}
