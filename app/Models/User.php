<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
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
