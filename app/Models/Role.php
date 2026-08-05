<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';

    /**
     * Roles staff accounts can be assigned - everything except the
     * customer-facing 'customer' role, which is reserved for storefront
     * registration and has no admin panel access.
     */
    public static function staffRoles(): array
    {
        $stmt = self::db()->query("SELECT * FROM roles WHERE slug != 'customer' ORDER BY name");

        return $stmt->fetchAll();
    }

    /**
     * Permission slugs currently granted to a role, for pre-checking
     * checkboxes in the permission matrix editor.
     */
    public static function permissionSlugs(int $roleId): array
    {
        $stmt = self::db()->prepare(
            'SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);

        return array_column($stmt->fetchAll(), 'slug');
    }

    /**
     * Replace a role's entire permission set with the given permission
     * IDs. Runs inside a transaction so a role is never left with a
     * partial set if something fails mid-way.
     */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $stmt->execute(['role_id' => $roleId]);

            $insert = $db->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );

            foreach ($permissionIds as $permissionId) {
                $insert->execute(['role_id' => $roleId, 'permission_id' => (int) $permissionId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
