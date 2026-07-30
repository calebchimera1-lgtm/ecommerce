<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ProductAttribute extends Model
{
    protected static string $table = 'product_attributes';

    public static function forProduct(int $productId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM product_attributes WHERE product_id = :product_id ORDER BY id ASC'
        );
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll();
    }

    public static function deleteForProduct(int $productId): void
    {
        $stmt = self::db()->prepare('DELETE FROM product_attributes WHERE product_id = :product_id');
        $stmt->execute(['product_id' => $productId]);
    }
}
