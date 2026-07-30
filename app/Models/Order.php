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
}
