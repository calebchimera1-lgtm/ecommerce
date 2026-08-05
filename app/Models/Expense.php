<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Was read-only from the dashboard's point of view since Module 9
 * (demo rows via database/seeders/expenses.sql, the same
 * optional-seed pattern Module 5 used for testimonials). Module 12
 * adds the full CRUD this doc comment used to say was deferred.
 */
final class Expense extends Model
{
    protected static string $table = 'expenses';

    public static function sumForMonth(int $year, int $month): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM expenses
             WHERE YEAR(expense_date) = :year AND MONTH(expense_date) = :month'
        );
        $stmt->execute(['year' => $year, 'month' => $month]);

        return (float) $stmt->fetch()['total'];
    }

    /**
     * @param array{category?:string,start_date?:string,end_date?:string} $filters
     */
    public static function paginateAll(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT e.*, u.first_name, u.last_name
             FROM expenses e
             LEFT JOIN users u ON u.id = e.created_by
             {$where}
             ORDER BY e.expense_date DESC, e.id DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM expenses e {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    public static function sumFiltered(array $filters = []): float
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $stmt = self::db()->prepare("SELECT COALESCE(SUM(e.amount), 0) AS total FROM expenses e {$where}");
        $stmt->execute($bindings);

        return (float) $stmt->fetch()['total'];
    }

    public static function categories(): array
    {
        $stmt = self::db()->query('SELECT DISTINCT category FROM expenses ORDER BY category');

        return array_column($stmt->fetchAll(), 'category');
    }

    private static function buildFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['category'] ?? '') !== '') {
            $conditions[] = 'e.category = :category';
            $bindings['category'] = $filters['category'];
        }

        if (($filters['start_date'] ?? '') !== '') {
            $conditions[] = 'e.expense_date >= :start_date';
            $bindings['start_date'] = $filters['start_date'];
        }

        if (($filters['end_date'] ?? '') !== '') {
            $conditions[] = 'e.expense_date <= :end_date';
            $bindings['end_date'] = $filters['end_date'];
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $bindings];
    }
}
