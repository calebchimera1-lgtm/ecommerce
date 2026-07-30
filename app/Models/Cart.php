<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

final class Cart extends Model
{
    protected static string $table = 'carts';

    /**
     * Look up the current visitor's cart (by user_id if logged in,
     * else by PHP session id) WITHOUT creating one. Used for read-only
     * paths like the nav's cart item count, so merely browsing the
     * site doesn't spawn an empty cart row per visitor.
     */
    public static function findExisting(): ?array
    {
        $userId = Auth::id();

        if ($userId !== null) {
            return self::findBy('user_id', $userId);
        }

        return self::findBySessionId(session_id());
    }

    public static function findOrCreate(): array
    {
        $existing = self::findExisting();

        if ($existing !== null) {
            return $existing;
        }

        $userId = Auth::id();
        $id = self::create([
            'user_id' => $userId,
            'session_id' => $userId === null ? session_id() : null,
        ]);

        return self::find($id);
    }

    private static function findBySessionId(string $sessionId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM carts WHERE session_id = :session_id AND user_id IS NULL LIMIT 1'
        );
        $stmt->execute(['session_id' => $sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function currentItemCount(): int
    {
        $cart = self::findExisting();

        if ($cart === null) {
            return 0;
        }

        $stmt = self::db()->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cart['id']]);

        return (int) $stmt->fetch()['total'];
    }

    /**
     * Moves a guest (session-identified) cart's items into the given
     * user's cart on login, combining quantities for matching product
     * + variant lines, then discards the now-empty guest cart. This is
     * what makes "save cart" meaningful for a customer who added items
     * before signing in.
     */
    public static function mergeSessionCartIntoUser(string $sessionId, int $userId): void
    {
        $sessionCart = self::findBySessionId($sessionId);

        if ($sessionCart === null) {
            return;
        }

        $userCart = self::findBy('user_id', $userId);

        if ($userCart === null) {
            $userCart = self::find(self::create(['user_id' => $userId, 'session_id' => null]));
        }

        foreach (CartItem::rawForCart((int) $sessionCart['id']) as $item) {
            CartItem::addOrIncrement(
                (int) $userCart['id'],
                (int) $item['product_id'],
                $item['product_attribute_id'] !== null ? (int) $item['product_attribute_id'] : null,
                (int) $item['quantity'],
                (string) $item['price']
            );
        }

        self::delete((int) $sessionCart['id']);
    }

    public static function applyCoupon(int $cartId, ?int $couponId): bool
    {
        return self::update($cartId, ['coupon_id' => $couponId]);
    }

    public static function setShippingMethod(int $cartId, ?int $shippingMethodId): bool
    {
        return self::update($cartId, ['shipping_method_id' => $shippingMethodId]);
    }
}
