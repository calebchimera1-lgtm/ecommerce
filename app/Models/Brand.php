<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;

final class Brand extends Model
{
    protected static string $table = 'brands';

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($name), $ignoreId);
    }

    public static function activeOrdered(): array
    {
        $stmt = self::db()->query('SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC');

        return $stmt->fetchAll();
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM brands WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
