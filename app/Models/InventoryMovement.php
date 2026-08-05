<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class InventoryMovement extends Model
{
    protected static string $table = 'inventory_movements';

    /**
     * @param array{product_id?:string|int} $filters
     */
    public static function paginateAll(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT im.*, p.name AS product_name, p.sku, u.first_name, u.last_name
             FROM inventory_movements im
             JOIN products p ON p.id = im.product_id
             LEFT JOIN users u ON u.id = im.created_by
             {$where}
             ORDER BY im.created_at DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM inventory_movements im {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildFilterWhere(array $filters): array
    {
        if (($filters['product_id'] ?? '') === '') {
            return ['', []];
        }

        return ['WHERE im.product_id = :product_id', ['product_id' => (int) $filters['product_id']]];
    }
}
