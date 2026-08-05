<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Permission extends Model
{
    protected static string $table = 'permissions';

    /**
     * All permissions grouped by module, e.g.
     * ['products' => [...], 'orders' => [...]] - feeds the role
     * permission matrix editor's per-module checkbox groups.
     */
    public static function allGroupedByModule(): array
    {
        $stmt = self::db()->query('SELECT * FROM permissions ORDER BY module, name');
        $grouped = [];

        foreach ($stmt->fetchAll() as $row) {
            $grouped[$row['module']][] = $row;
        }

        return $grouped;
    }
}
