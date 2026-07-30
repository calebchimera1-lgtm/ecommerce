<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Wishlist extends Model
{
    protected static string $table = 'wishlists';

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT w.*, p.name, p.slug, p.price, p.sale_price,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_path
             FROM wishlists w
             JOIN products p ON p.id = w.product_id
             WHERE w.user_id = :user_id AND p.deleted_at IS NULL
             ORDER BY w.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function exists(int $userId, int $productId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM wishlists WHERE user_id = :user_id AND product_id = :product_id'
        );
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

        return (int) $stmt->fetch()['total'] > 0;
    }

    /**
     * @return bool true if the product is now in the wishlist, false if it was just removed.
     */
    public static function toggle(int $userId, int $productId): bool
    {
        if (self::exists($userId, $productId)) {
            $stmt = self::db()->prepare(
                'DELETE FROM wishlists WHERE user_id = :user_id AND product_id = :product_id'
            );
            $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

            return false;
        }

        self::create(['user_id' => $userId, 'product_id' => $productId]);

        return true;
    }
}
