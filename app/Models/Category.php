<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;

final class Category extends Model
{
    protected static string $table = 'categories';

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
}
