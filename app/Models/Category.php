<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Model;
use App\Core\Str;

final class Category extends Model
{
    protected static string $table = 'categories';

    /**
     * Read on nearly every page request (the main nav's mega-menu,
     * plus the homepage) but changes only when an admin edits a
     * category - a long TTL as a safety net, backed up by explicit
     * cache invalidation (self::invalidateCache()) from every admin
     * action that can change this result, so an admin's edit is
     * visible immediately rather than after up to an hour of staleness.
     */
    private const ACTIVE_ORDERED_CACHE_KEY = 'categories.active_ordered';
    private const ACTIVE_ORDERED_CACHE_TTL = 3600;

    /**
     * Flat list ordered as a tree (parents immediately followed by
     * their children), each row annotated with 'depth' for indentation
     * in admin select dropdowns and list views.
     */
    public static function tree(): array
    {
        return self::buildTree(self::all('sort_order', 'ASC'), null, 0);
    }

    private static function buildTree(array $all, ?int $parentId, int $depth): array
    {
        $result = [];

        foreach ($all as $row) {
            $rowParentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;

            if ($rowParentId === $parentId) {
                $row['depth'] = $depth;
                $result[] = $row;
                $result = array_merge($result, self::buildTree($all, (int) $row['id'], $depth + 1));
            }
        }

        return $result;
    }

    /**
     * A category and all of its descendants - used to stop an admin
     * from assigning a category as its own (grand)parent, which would
     * otherwise create a cycle.
     */
    public static function selfAndDescendantIds(int $categoryId): array
    {
        $all = self::all();
        $ids = [$categoryId];

        $collect = function (int $parentId) use (&$collect, &$ids, $all): void {
            foreach ($all as $row) {
                if ((int) ($row['parent_id'] ?? -1) === $parentId) {
                    $ids[] = (int) $row['id'];
                    $collect((int) $row['id']);
                }
            }
        };

        $collect($categoryId);

        return $ids;
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($name), $ignoreId);
    }

    public static function activeOrdered(): array
    {
        return Cache::remember(self::ACTIVE_ORDERED_CACHE_KEY, self::ACTIVE_ORDERED_CACHE_TTL, static function (): array {
            $stmt = self::db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');

            return $stmt->fetchAll();
        });
    }

    /**
     * Called from Admin\CategoryController on every create/update/
     * delete/status-toggle - anything that could change what
     * activeOrdered() returns - so a category edit shows up on the
     * live site immediately rather than waiting out the cache TTL.
     */
    public static function invalidateCache(): void
    {
        Cache::forget(self::ACTIVE_ORDERED_CACHE_KEY);
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
