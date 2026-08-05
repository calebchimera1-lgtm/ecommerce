<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class OrderItem extends Model
{
    protected static string $table = 'order_items';

    public static function forOrder(int $orderId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    /**
     * Best-selling products by total quantity sold across all orders.
     * Joins live product data (name/slug/image) rather than relying on
     * the order_items snapshot, so a since-renamed product shows its
     * current name; a since-deleted product is simply excluded.
     */
    public static function bestSelling(int $limit = 5): array
    {
        $stmt = self::db()->prepare(
            'SELECT oi.product_id, p.name, p.slug, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_path
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE p.deleted_at IS NULL
             GROUP BY oi.product_id, p.name, p.slug
             ORDER BY units_sold DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Same shape as bestSelling(), bounded to a date range instead of
     * all-time - the product performance report's data source.
     * $limit = 0 means no limit (used for full CSV/Excel/PDF exports,
     * where the report should contain every product sold in range, not
     * just a dashboard-sized top-N).
     */
    public static function bestSellingBetween(string $startDate, string $endDate, int $limit = 0): array
    {
        $sql = 'SELECT oi.product_id, p.name, p.sku, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
                GROUP BY oi.product_id, p.name, p.sku
                ORDER BY units_sold DESC';

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
}
