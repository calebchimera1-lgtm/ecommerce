<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Order extends Model
{
    protected static string $table = 'orders';

    public static function findByNumberForUser(string $orderNumber, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM orders WHERE order_number = :order_number AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['order_number' => $orderNumber, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{status?:string} $filters
     */
    public static function paginateAll(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);

        $sql = 'SELECT o.*, u.first_name, u.last_name, u.email
                FROM orders o
                JOIN users u ON u.id = o.user_id'
              . ($where !== '' ? ' WHERE ' . $where : '')
              . ' ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset';

        $offset = (max(1, $page) - 1) * $perPage;
        $stmt = self::db()->prepare($sql);

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
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $sql = 'SELECT COUNT(*) AS total FROM orders o' . ($where !== '' ? ' WHERE ' . $where : '');

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'o.status = :status';
            $bindings['status'] = $filters['status'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    public static function updateStatus(int $orderId, string $status, ?string $note, ?int $changedBy): void
    {
        self::update($orderId, ['status' => $status]);

        OrderStatusHistory::create([
            'order_id' => $orderId,
            'status' => $status,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }

    // -------------------------------------------------------------
    // Dashboard / analytics aggregates. "Sales" = gross order totals
    // regardless of payment status (bookings); "Revenue" = totals for
    // orders that have actually been paid. Kept as two distinct
    // figures rather than conflating them.
    // -------------------------------------------------------------

    public static function sumSalesForDate(string $date): float
    {
        $stmt = self::db()->prepare('SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE DATE(created_at) = :date');
        $stmt->execute(['date' => $date]);

        return (float) $stmt->fetch()['total'];
    }

    public static function sumSalesForMonth(int $year, int $month): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE YEAR(created_at) = :year AND MONTH(created_at) = :month'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (float) $stmt->fetch()['total'];
    }

    public static function sumPaidRevenueAllTime(): float
    {
        $stmt = self::db()->query("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE payment_status = 'paid'");

        return (float) $stmt->fetch()['total'];
    }

    public static function sumPaidRevenueForMonth(int $year, int $month): float
    {
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(total), 0) AS total FROM orders
             WHERE payment_status = 'paid' AND YEAR(created_at) = :year AND MONTH(created_at) = :month"
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (float) $stmt->fetch()['total'];
    }

    public static function countAllTime(): int
    {
        $stmt = self::db()->query('SELECT COUNT(*) AS total FROM orders');

        return (int) $stmt->fetch()['total'];
    }

    public static function countForDate(string $date): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS total FROM orders WHERE DATE(created_at) = :date');
        $stmt->execute(['date' => $date]);

        return (int) $stmt->fetch()['total'];
    }

    public static function latest(int $limit = 5): array
    {
        $stmt = self::db()->prepare(
            'SELECT o.*, u.first_name, u.last_name
             FROM orders o
             JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Daily gross sales totals for the last $days days (including
     * today), zero-filled for days with no orders - feeds the
     * dashboard's sales trend chart.
     */
    public static function dailySalesTrend(int $days = 14): array
    {
        $stmt = self::db()->prepare(
            'SELECT DATE(created_at) AS day, SUM(total) AS total
             FROM orders
             WHERE created_at >= :since
             GROUP BY DATE(created_at)'
        );
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stmt->execute(['since' => $since]);
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
     * Order count grouped by status - feeds the dashboard's order
     * status breakdown chart.
     */
    public static function statusBreakdown(): array
    {
        $stmt = self::db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
        $counts = [];

        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
