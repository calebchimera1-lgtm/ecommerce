<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * One row per distinct vendor present in a customer order - the
 * "split" Module 15 promised: each vendor fulfills and gets paid for
 * only their own slice of an order, tracked independently of the
 * parent order and of any other vendor sharing it. Created by
 * OrderPlacementService at checkout time, one per vendor whose
 * products appear in the cart; never created for platform-owned
 * items, which stay under the parent `orders` row's own status exactly
 * as they did before this table existed.
 */
final class VendorOrder extends Model
{
    protected static string $table = 'vendor_orders';

    public static function forOrder(int $orderId): array
    {
        $stmt = self::db()->prepare(
            'SELECT vo.*, v.store_name AS vendor_store_name
             FROM vendor_orders vo
             JOIN vendors v ON v.id = vo.vendor_id
             WHERE vo.order_id = :order_id
             ORDER BY vo.id ASC'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    /**
     * A single vendor sub-order, but only if it belongs to this vendor
     * - the ownership check every vendor-portal order action goes
     * through, so a vendor can never reach another vendor's sub-order
     * (or the customer/payment details behind it) by guessing an id.
     */
    public static function findForVendor(int $id, int $vendorId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT vo.*, o.order_number, o.created_at AS order_created_at, o.status AS order_status
             FROM vendor_orders vo
             JOIN orders o ON o.id = vo.order_id
             WHERE vo.id = :id AND vo.vendor_id = :vendor_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'vendor_id' => $vendorId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{status?:string,payout_status?:string} $filters
     */
    public static function paginateForVendor(int $vendorId, int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildVendorWhere($vendorId, $filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT vo.*, o.order_number, o.created_at AS order_created_at,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.vendor_order_id = vo.id) AS item_count
             FROM vendor_orders vo
             JOIN orders o ON o.id = vo.order_id
             {$where}
             ORDER BY vo.created_at DESC
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

    public static function countForVendor(int $vendorId, array $filters = []): int
    {
        [$where, $bindings] = self::buildVendorWhere($vendorId, $filters);
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) AS total FROM vendor_orders vo JOIN orders o ON o.id = vo.order_id {$where}"
        );
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildVendorWhere(int $vendorId, array $filters): array
    {
        $conditions = ['vo.vendor_id = :vendor_id'];
        $bindings = ['vendor_id' => $vendorId];

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'vo.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['payout_status'] ?? '') !== '') {
            $conditions[] = 'vo.payout_status = :payout_status';
            $bindings['payout_status'] = $filters['payout_status'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * Total unpaid payout balance owed to a vendor across every order
     * - the number a vendor sees as "what am I owed right now" and an
     * admin sees before wiring a manual payout.
     */
    public static function unpaidBalanceForVendor(int $vendorId): float
    {
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(payout_amount), 0) AS total FROM vendor_orders
             WHERE vendor_id = :vendor_id AND payout_status = 'unpaid'"
        );
        $stmt->execute(['vendor_id' => $vendorId]);

        return (float) $stmt->fetch()['total'];
    }

    public static function paidToDateForVendor(int $vendorId): float
    {
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(payout_amount), 0) AS total FROM vendor_orders
             WHERE vendor_id = :vendor_id AND payout_status = 'paid'"
        );
        $stmt->execute(['vendor_id' => $vendorId]);

        return (float) $stmt->fetch()['total'];
    }

    /**
     * Every vendor with at least one order, each annotated with its
     * unpaid balance and lifetime paid total - the admin payouts
     * overview's data source, sorted so the vendors owed the most
     * float to the top.
     */
    public static function balancesByVendor(): array
    {
        $stmt = self::db()->query(
            "SELECT v.id AS vendor_id, v.store_name,
                    COALESCE(SUM(CASE WHEN vo.payout_status = 'unpaid' THEN vo.payout_amount ELSE 0 END), 0) AS unpaid_balance,
                    COALESCE(SUM(CASE WHEN vo.payout_status = 'paid' THEN vo.payout_amount ELSE 0 END), 0) AS paid_to_date,
                    COUNT(*) AS order_count
             FROM vendor_orders vo
             JOIN vendors v ON v.id = vo.vendor_id
             GROUP BY v.id, v.store_name
             ORDER BY unpaid_balance DESC"
        );

        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status, ?string $note, ?int $changedBy): void
    {
        self::update($id, ['status' => $status]);

        VendorOrderStatusHistory::create([
            'vendor_order_id' => $id,
            'status' => $status,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }

    public static function markPaid(int $id, int $adminId, ?string $reference): void
    {
        self::update($id, [
            'payout_status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'paid_by' => $adminId,
            'payout_reference' => $reference,
        ]);
    }

    // -------------------------------------------------------------
    // Analytics aggregates for a vendor's own dashboard - the vendor
    // counterpart to Order's dashboard aggregates (Module 9), scoped
    // to one vendor_id throughout. "Sales" here means the vendor's own
    // subtotal (their gross, before commission) for the orders in
    // question, not the platform-wide order total.
    // -------------------------------------------------------------

    public static function sumSubtotalForDate(int $vendorId, string $date): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(subtotal), 0) AS total FROM vendor_orders
             WHERE vendor_id = :vendor_id AND DATE(created_at) = :date'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'date' => $date]);

        return (float) $stmt->fetch()['total'];
    }

    public static function countForDate(int $vendorId, string $date): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM vendor_orders WHERE vendor_id = :vendor_id AND DATE(created_at) = :date'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'date' => $date]);

        return (int) $stmt->fetch()['total'];
    }

    public static function sumSubtotalForMonth(int $vendorId, int $year, int $month): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(subtotal), 0) AS total FROM vendor_orders
             WHERE vendor_id = :vendor_id AND YEAR(created_at) = :year AND MONTH(created_at) = :month'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'year' => $year, 'month' => $month]);

        return (float) $stmt->fetch()['total'];
    }

    public static function sumPayoutForMonth(int $vendorId, int $year, int $month): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(payout_amount), 0) AS total FROM vendor_orders
             WHERE vendor_id = :vendor_id AND YEAR(created_at) = :year AND MONTH(created_at) = :month'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'year' => $year, 'month' => $month]);

        return (float) $stmt->fetch()['total'];
    }

    public static function sumSubtotalAllTime(int $vendorId): float
    {
        $stmt = self::db()->prepare('SELECT COALESCE(SUM(subtotal), 0) AS total FROM vendor_orders WHERE vendor_id = :vendor_id');
        $stmt->execute(['vendor_id' => $vendorId]);

        return (float) $stmt->fetch()['total'];
    }

    /**
     * Daily gross-sales totals for a vendor over the last $days days
     * (including today), zero-filled for days with no orders -
     * mirrors Order::dailySalesTrend() exactly, scoped to one vendor.
     */
    public static function dailySalesTrend(int $vendorId, int $days = 14): array
    {
        $stmt = self::db()->prepare(
            'SELECT DATE(created_at) AS day, SUM(subtotal) AS total
             FROM vendor_orders
             WHERE vendor_id = :vendor_id AND created_at >= :since
             GROUP BY DATE(created_at)'
        );
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stmt->execute(['vendor_id' => $vendorId, 'since' => $since]);
        $byDay = [];

        foreach ($stmt->fetchAll() as $row) {
            $byDay[$row['day']] = (float) $row['total'];
        }

        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = ['day' => $day, 'total' => $byDay[$day] ?? 0.0];
        }

        return $trend;
    }

    /**
     * A vendor's own sub-order count grouped by fulfillment status -
     * mirrors Order::statusBreakdown(), scoped to one vendor.
     */
    public static function statusBreakdown(int $vendorId): array
    {
        $stmt = self::db()->prepare('SELECT status, COUNT(*) AS total FROM vendor_orders WHERE vendor_id = :vendor_id GROUP BY status');
        $stmt->execute(['vendor_id' => $vendorId]);
        $counts = [];

        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
