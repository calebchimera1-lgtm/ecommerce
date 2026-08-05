<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CartItem extends Model
{
    protected static string $table = 'cart_items';

    /**
     * Cart items joined with product/variant display data, for the
     * cart page.
     */
    public static function forCart(int $cartId): array
    {
        $stmt = self::db()->prepare(
            'SELECT ci.*, p.name AS product_name, p.slug AS product_slug, p.sku AS product_sku,
                    p.stock_quantity AS product_stock, p.vendor_id, p.category_id,
                    pa.attribute_name, pa.attribute_value, pa.stock_quantity AS attribute_stock, pa.sku_suffix,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_path
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_attributes pa ON pa.id = ci.product_attribute_id
             WHERE ci.cart_id = :cart_id
             ORDER BY ci.id ASC'
        );
        $stmt->execute(['cart_id' => $cartId]);

        return $stmt->fetchAll();
    }

    /**
     * Raw cart_items rows (no joins) - used when merging a guest cart
     * into a user's cart, where only the underlying columns matter.
     */
    public static function rawForCart(int $cartId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cartId]);

        return $stmt->fetchAll();
    }

    public static function findMatching(int $cartId, int $productId, ?int $attributeId): ?array
    {
        $sql = 'SELECT * FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND '
            . ($attributeId === null ? 'product_attribute_id IS NULL' : 'product_attribute_id = :attribute_id');

        $bindings = ['cart_id' => $cartId, 'product_id' => $productId];

        if ($attributeId !== null) {
            $bindings['attribute_id'] = $attributeId;
        }

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function addOrIncrement(int $cartId, int $productId, ?int $attributeId, int $quantity, string $price): void
    {
        $existing = self::findMatching($cartId, $productId, $attributeId);

        if ($existing !== null) {
            self::update((int) $existing['id'], [
                'quantity' => (int) $existing['quantity'] + $quantity,
                'price' => $price,
            ]);

            return;
        }

        self::create([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'product_attribute_id' => $attributeId,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    public static function clearForCart(int $cartId): void
    {
        $stmt = self::db()->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cartId]);
    }
}
