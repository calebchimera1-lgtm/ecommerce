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
            // Two distinct placeholders bound to the same value - PDO's
            // native (non-emulated) MySQL prepares reject reusing one
            // named placeholder twice in a single query.
            $conditions[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku)';
            $bindings['search_name'] = '%' . $filters['search'] . '%';
            $bindings['search_sku'] = '%' . $filters['search'] . '%';
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

    // -------------------------------------------------------------
    // Public storefront queries - always restricted to is_active = 1
    // and deleted_at IS NULL, unlike the admin queries above which
    // show every non-deleted product regardless of status.
    // -------------------------------------------------------------

    private const PRIMARY_IMAGE_SUBQUERY = '(SELECT image_path FROM product_images pi
        WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image';

    /**
     * @param array{search?:string,category_id?:string,brand_id?:string,min_price?:string,max_price?:string} $filters
     */
    public static function publicPaginate(int $page, int $perPage, array $filters, string $sort = 'newest'): array
    {
        [$where, $bindings] = self::buildPublicWhere($filters);
        $orderBy = match ($sort) {
            'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
            'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
            'name_asc' => 'p.name ASC',
            default => 'p.created_at DESC',
        };

        $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                       b.name AS brand_name, b.slug AS brand_slug,
                       ' . self::PRIMARY_IMAGE_SUBQUERY . '
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id'
              . ($where !== '' ? ' WHERE ' . $where : '')
              . ' ORDER BY ' . $orderBy
              . ' LIMIT :limit OFFSET :offset';

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

    public static function publicCount(array $filters): int
    {
        [$where, $bindings] = self::buildPublicWhere($filters);
        $sql = 'SELECT COUNT(*) AS total FROM products p' . ($where !== '' ? ' WHERE ' . $where : '');

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildPublicWhere(array $filters): array
    {
        $conditions = ['p.deleted_at IS NULL', 'p.is_active = 1'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.name LIKE :search_name OR p.short_description LIKE :search_desc)';
            $bindings['search_name'] = '%' . $filters['search'] . '%';
            $bindings['search_desc'] = '%' . $filters['search'] . '%';
        }

        if (($filters['category_id'] ?? '') !== '') {
            $conditions[] = 'p.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['brand_id'] ?? '') !== '') {
            $conditions[] = 'p.brand_id = :brand_id';
            $bindings['brand_id'] = (int) $filters['brand_id'];
        }

        if (($filters['min_price'] ?? '') !== '') {
            $conditions[] = 'COALESCE(p.sale_price, p.price) >= :min_price';
            $bindings['min_price'] = (float) $filters['min_price'];
        }

        if (($filters['max_price'] ?? '') !== '') {
            $conditions[] = 'COALESCE(p.sale_price, p.price) <= :max_price';
            $bindings['max_price'] = (float) $filters['max_price'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    b.name AS brand_name, b.slug AS brand_slug
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.slug = :slug AND p.deleted_at IS NULL AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function incrementViewCount(int $id): void
    {
        $stmt = self::db()->prepare('UPDATE products SET view_count = view_count + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function featured(int $limit = 8): array
    {
        return self::publicLimitedQuery('p.is_featured = 1', 'p.created_at DESC', $limit);
    }

    public static function newArrivals(int $limit = 8): array
    {
        return self::publicLimitedQuery('1 = 1', 'p.created_at DESC', $limit);
    }

    public static function onSale(int $limit = 8): array
    {
        return self::publicLimitedQuery('p.sale_price IS NOT NULL', 'p.created_at DESC', $limit);
    }

    public static function trending(int $limit = 8): array
    {
        return self::publicLimitedQuery('1 = 1', 'p.view_count DESC, p.created_at DESC', $limit);
    }

    private static function publicLimitedQuery(string $extraCondition, string $orderBy, int $limit): array
    {
        $sql = 'SELECT p.*, c.name AS category_name,
                       ' . self::PRIMARY_IMAGE_SUBQUERY . '
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.deleted_at IS NULL AND p.is_active = 1 AND ' . $extraCondition . '
                ORDER BY ' . $orderBy . '
                LIMIT :limit';

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
