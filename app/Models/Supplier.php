<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Supplier extends Model
{
    protected static string $table = 'suppliers';

    public static function active(): array
    {
        $stmt = self::db()->query('SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name');

        return $stmt->fetchAll();
    }

    /**
     * @param array{search?:string} $filters
     */
    public static function paginateAll(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT * FROM suppliers {$where} ORDER BY name LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countAll(array $filters = []): int
    {
        [$where, $bindings] = self::buildFilterWhere($filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM suppliers {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildFilterWhere(array $filters): array
    {
        if (($filters['search'] ?? '') === '') {
            return ['', []];
        }

        return ['WHERE name LIKE :search', ['search' => '%' . $filters['search'] . '%']];
    }
}
