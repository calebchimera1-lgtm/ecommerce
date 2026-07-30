<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CouponUsage extends Model
{
    protected static string $table = 'coupon_usages';

    /**
     * NOTE: rows here are only created when an order is placed
     * (coupon_usages.order_id is NOT NULL in the schema), which is
     * Module 7 (checkout). Until then this always returns 0 - the
     * per-user coupon limit check that calls this is correctly wired
     * for when checkout exists, but is a no-op today.
     */
    public static function countForUser(int $couponId, int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM coupon_usages WHERE coupon_id = :coupon_id AND user_id = :user_id'
        );
        $stmt->execute(['coupon_id' => $couponId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'];
    }
}
