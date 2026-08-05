<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    /**
     * @param array{search?:string,status?:string} $filters
     */
    public static function paginateCustomers(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildCustomerFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT u.* FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'customer' {$where}
             ORDER BY u.created_at DESC
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

    public static function countCustomersFiltered(array $filters = []): int
    {
        [$where, $bindings] = self::buildCustomerFilterWhere($filters);

        $stmt = self::db()->prepare(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'customer' {$where}"
        );
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildCustomerFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(u.first_name LIKE :search_name OR u.last_name LIKE :search_name OR u.email LIKE :search_email)';
            $bindings['search_name'] = '%' . $filters['search'] . '%';
            $bindings['search_email'] = '%' . $filters['search'] . '%';
        }

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'u.status = :status';
            $bindings['status'] = $filters['status'];
        }

        return [$conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * Staff/admin accounts - every role except the 'customer' role.
     */
    public static function paginateStaff(int $page, int $perPage): array
    {
        $offset = (max(1, $page) - 1) * $perPage;
        $stmt = self::db()->prepare(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug != 'customer'
             ORDER BY u.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countStaff(): int
    {
        $stmt = self::db()->query(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug != 'customer'"
        );

        return (int) $stmt->fetch()['total'];
    }

    public static function updateStatus(int $id, string $status): void
    {
        self::update($id, ['status' => $status]);
    }

    /**
     * Customers ranked by total order value within a date range - the
     * customer report's data source. $limit = 0 means no limit (full
     * export); the dashboard-sized top-N case isn't currently used
     * elsewhere but follows the same convention as
     * OrderItem::bestSellingBetween() for consistency.
     */
    public static function topCustomers(string $startDate, string $endDate, int $limit = 0): array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email,
                       COUNT(o.id) AS order_count,
                       SUM(o.total) AS total_spent,
                       MAX(o.created_at) AS last_order_at
                FROM users u
                JOIN orders o ON o.user_id = u.id
                WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
                GROUP BY u.id, u.first_name, u.last_name, u.email
                ORDER BY total_spent DESC";

        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);

        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countCustomers(): int
    {
        $stmt = self::db()->query(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'customer'"
        );

        return (int) $stmt->fetch()['total'];
    }

    public static function countNewCustomersForMonth(int $year, int $month): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'customer' AND YEAR(u.created_at) = :year AND MONTH(u.created_at) = :month"
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (int) $stmt->fetch()['total'];
    }

    public static function recentCustomers(int $limit = 5): array
    {
        $stmt = self::db()->prepare(
            "SELECT u.* FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'customer'
             ORDER BY u.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
