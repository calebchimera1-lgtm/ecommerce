<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;

final class BlogCategory extends Model
{
    protected static string $table = 'blog_categories';

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($name), $ignoreId);
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }
}
