<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Read-only from the dashboard's point of view in this module - full
 * expense entry/management (create/edit/delete, categorization,
 * export) is Module 12's job. Demo rows come from
 * database/seeders/expenses.sql, the same optional-seed pattern
 * Module 5 used for testimonials.
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
}
