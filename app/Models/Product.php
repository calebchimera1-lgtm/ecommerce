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
     * @param array{search?:string,category_id?:string|int,brand_id?:string|int,approval_status?:string} $filters
     */
    public static function paginateWithFilters(int $page, int $perPage, array $filters): array
    {
        [$where, $bindings] = self::buildFilterWhere($filters);

        $sql = 'SELECT p.*, c.name AS category_name, b.name AS brand_name, v.store_name AS vendor_store_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                LEFT JOIN vendors v ON v.id = p.vendor_id'
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

        if (($filters['approval_status'] ?? '') !== '') {
            $conditions[] = 'p.approval_status = :approval_status';
            $bindings['approval_status'] = $filters['approval_status'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT p.*, c.name AS category_name, b.name AS brand_name, v.store_name AS vendor_store_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN vendors v ON v.id = p.vendor_id
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

    public static function approveListing(int $id): void
    {
        self::update($id, ['approval_status' => 'approved', 'rejection_reason' => null]);
    }

    public static function rejectListing(int $id, string $reason): void
    {
        self::update($id, ['approval_status' => 'rejected', 'rejection_reason' => $reason]);
    }

    // -------------------------------------------------------------
    // Vendor-portal queries - always scoped to a single vendor_id, so
    // a vendor can never read or touch another vendor's products.
    // -------------------------------------------------------------

    /**
     * @param array{search?:string,approval_status?:string} $filters
     */
    public static function paginateForVendor(int $vendorId, int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildVendorWhere($vendorId, $filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             {$where}
             ORDER BY p.created_at DESC
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

    public static function countForVendor(int $vendorId, array $filters = []): int
    {
        [$where, $bindings] = self::buildVendorWhere($vendorId, $filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM products p {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildVendorWhere(int $vendorId, array $filters): array
    {
        $conditions = ['p.vendor_id = :vendor_id', 'p.deleted_at IS NULL'];
        $bindings = ['vendor_id' => $vendorId];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku)';
            $bindings['search_name'] = '%' . $filters['search'] . '%';
            $bindings['search_sku'] = '%' . $filters['search'] . '%';
        }

        if (($filters['approval_status'] ?? '') !== '') {
            $conditions[] = 'p.approval_status = :approval_status';
            $bindings['approval_status'] = $filters['approval_status'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * A single product, but only if it belongs to this vendor - the
     * ownership check every vendor-portal edit/delete/image action
     * goes through before touching a row, so a vendor can never
     * reach another vendor's product by guessing an id in the URL.
     */
    public static function findForVendor(int $id, int $vendorId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.vendor_id = :vendor_id AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'vendor_id' => $vendorId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Vendor product counts by approval status, for the vendor
     * dashboard's stat tiles.
     */
    public static function statusCountsForVendor(int $vendorId): array
    {
        $stmt = self::db()->prepare(
            "SELECT approval_status, COUNT(*) AS total
             FROM products
             WHERE vendor_id = :vendor_id AND deleted_at IS NULL
             GROUP BY approval_status"
        );
        $stmt->execute(['vendor_id' => $vendorId]);

        $counts = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['approval_status']] = (int) $row['total'];
        }

        return $counts;
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
        $conditions = ['p.deleted_at IS NULL', 'p.is_active = 1', "p.approval_status = 'approved'"];
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
             WHERE p.slug = :slug AND p.deleted_at IS NULL AND p.is_active = 1 AND p.approval_status = \'approved\'
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
                WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.approval_status = \'approved\' AND ' . $extraCondition . '
                ORDER BY ' . $orderBy . '
                LIMIT :limit';

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Every active, non-deleted product's slug and last-modified
     * timestamp - the sitemap's data source (deliberately minimal
     * columns, since a sitemap doesn't need full product rows).
     */
    public static function allActiveForSitemap(): array
    {
        $stmt = self::db()->query(
            "SELECT slug, updated_at FROM products WHERE deleted_at IS NULL AND is_active = 1 AND approval_status = 'approved'"
        );

        return $stmt->fetchAll();
    }

    /**
     * Minimal id/name/sku list for purchase order line-item dropdowns -
     * every non-deleted product, active or not (a discontinued-but-not-
     * deleted product can still be legitimately restocked to sell
     * through remaining demand).
     */
    public static function forSelect(): array
    {
        $stmt = self::db()->query(
            'SELECT id, name, sku FROM products WHERE deleted_at IS NULL ORDER BY name'
        );

        return $stmt->fetchAll();
    }

    /**
     * Applies a signed delta to a product's stock (positive for
     * incoming stock, negative for outgoing) - the single place stock
     * quantity is mutated outside of order placement, so purchase
     * order receiving and manual inventory adjustments share one code
     * path instead of each re-implementing the arithmetic.
     */
    public static function adjustStock(int $id, int $delta): void
    {
        $stmt = self::db()->prepare('UPDATE products SET stock_quantity = stock_quantity + :delta WHERE id = :id');
        $stmt->execute(['delta' => $delta, 'id' => $id]);
    }

    /**
     * @param array{search?:string,stock?:string} $filters stock: 'low'|'out'|''
     */
    public static function paginateInventory(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildInventoryWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT p.*, s.name AS supplier_name
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             {$where}
             ORDER BY p.stock_quantity ASC
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

    public static function countInventory(array $filters = []): int
    {
        [$where, $bindings] = self::buildInventoryWhere($filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM products p {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildInventoryWhere(array $filters): array
    {
        $conditions = ['p.deleted_at IS NULL'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku)';
            $bindings['search_name'] = '%' . $filters['search'] . '%';
            $bindings['search_sku'] = '%' . $filters['search'] . '%';
        }

        if (($filters['stock'] ?? '') === 'low') {
            $conditions[] = 'p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0';
        } elseif (($filters['stock'] ?? '') === 'out') {
            $conditions[] = 'p.stock_quantity <= 0';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * Every non-deleted product with its current stock valuation
     * (stock_quantity * cost_price) - the inventory report's data
     * source. Unlike the Inventory admin page this isn't paginated;
     * reports are meant to be exported in full, not browsed a page at
     * a time.
     */
    public static function inventoryReportRows(): array
    {
        $stmt = self::db()->query(
            'SELECT p.sku, p.name, c.name AS category_name, s.name AS supplier_name,
                    p.stock_quantity, p.low_stock_threshold, p.cost_price,
                    (p.stock_quantity * COALESCE(p.cost_price, 0)) AS stock_value
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.name'
        );

        return $stmt->fetchAll();
    }

    public static function lowStock(int $limit = 10): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM products
             WHERE deleted_at IS NULL AND is_active = 1 AND stock_quantity <= low_stock_threshold
             ORDER BY stock_quantity ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
