<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ProductImage extends Model
{
    protected static string $table = 'product_images';

    public static function forProduct(int $productId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll();
    }

    public static function clearPrimary(int $productId): void
    {
        $stmt = self::db()->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id');
        $stmt->execute(['product_id' => $productId]);
    }
}
