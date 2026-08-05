<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;
use PDO;

/**
 * A vendor's business profile - one row per vendor user account
 * (`vendors.user_id` is unique). The account's login identity and
 * password live on `users` like every other account type; this table
 * holds only what's specific to being a seller: store name/slug,
 * approval status, and payout details.
 */
final class Vendor extends Model
{
    protected static string $table = 'vendors';

    public static function generateSlug(string $storeName, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($storeName), $ignoreId);
    }

    public static function findByUserId(int $userId): ?array
    {
        return self::findBy('user_id', $userId);
    }

    public static function findWithUser(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT v.*, u.email, u.first_name, u.last_name, u.status AS user_status
             FROM vendors v
             JOIN users u ON u.id = v.user_id
             WHERE v.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{status?:string,search?:string} $filters
     */
    public static function paginateAdmin(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT v.*, u.email, u.first_name, u.last_name
             FROM vendors v
             JOIN users u ON u.id = v.user_id
             {$where}
             ORDER BY v.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countAdmin(array $filters = []): int
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM vendors v JOIN users u ON u.id = v.user_id {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'v.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(v.store_name LIKE :search_store OR u.email LIKE :search_email)';
            $bindings['search_store'] = '%' . $filters['search'] . '%';
            $bindings['search_email'] = '%' . $filters['search'] . '%';
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    public static function approve(int $id, int $approvedBy): void
    {
        self::update($id, [
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => null,
        ]);
    }

    public static function reject(int $id, string $reason): void
    {
        self::update($id, [
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public static function suspend(int $id): void
    {
        self::update($id, ['status' => 'suspended']);
    }

    /**
     * Restores a suspended vendor to approved - not available from
     * 'pending'/'rejected', which go through approve()/reject()
     * instead (those set approved_by/approved_at or a rejection
     * reason that reactivate() shouldn't touch).
     */
    public static function reactivate(int $id): void
    {
        self::update($id, ['status' => 'approved']);
    }
}
