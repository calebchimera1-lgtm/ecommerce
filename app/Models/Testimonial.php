<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Testimonial extends Model
{
    protected static string $table = 'testimonials';

    public static function activeOrdered(int $limit = 6): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
