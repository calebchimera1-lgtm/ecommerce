<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;
use PDO;

final class Product extends Model
{
    protected static string $table = 'products';

    public static function generateSku(): string
    {
        do {
            $sku = 'KYM-' . strtoupper(bin2hex(random_bytes(4)));
        } while (self::findBy('sku', $sku) !== null);

        return $sku;
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($name), $ignoreId);
    }

    /**
     * @param array{search?:string,category_id?:string|int,brand_id?:string|int} $filters
     */
    public static function paginateWithFilters(int $page, int $perPage, array $filters): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);

        $sql = 'SELECT p.*, c.name AS category_name, b.name AS brand_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id'
              . ($where !== '' ? ' WHERE ' . $where : '')
              . ' ORDER BY p.created_at DESC
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

    public static function countWithFilters(array $filters): int
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $sql = 'SELECT COUNT(*) AS total FROM products p' . ($where !== '' ? ' WHERE ' . $where : '');

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildFilterWhere(array $filters): array
    {
        $conditions = ['p.deleted_at IS NULL'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.name LIKE :search OR p.sku LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['category_id'] ?? '') !== '') {
            $conditions[] = 'p.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['brand_id'] ?? '') !== '') {
            $conditions[] = 'p.brand_id = :brand_id';
            $bindings['brand_id'] = (int) $filters['brand_id'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT p.*, c.name AS category_name, b.name AS brand_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.id = :id AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function softDelete(int $id): bool
    {
        return self::update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }
}
