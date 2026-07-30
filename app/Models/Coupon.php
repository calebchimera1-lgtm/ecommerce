<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Coupon extends Model
{
    protected static string $table = 'coupons';

    public static function findValidByCode(string $code): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM coupons
             WHERE code = :code AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (expires_at IS NULL OR expires_at >= NOW())
             LIMIT 1'
        );
        $stmt->execute(['code' => mb_strtoupper($code)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
