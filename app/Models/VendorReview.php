<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * A customer's rating of a vendor as a seller - distinct from
 * ProductReview, which rates a specific item. Mirrors ProductReview's
 * shape and moderation workflow exactly (rating/title/comment,
 * is_approved queue, one review per reviewer), the only structural
 * difference being what's being rated: a vendor (via vendor_id)
 * instead of a product.
 */
final class VendorReview extends Model
{
    protected static string $table = 'vendor_reviews';

    /**
     * @param array{status?:string} $filters status: 'pending'|'approved'|''
     */
    public static function paginateAdmin(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT vr.*, u.first_name, u.last_name, v.store_name AS vendor_store_name, v.slug AS vendor_slug
             FROM vendor_reviews vr
             JOIN users u ON u.id = vr.user_id
             JOIN vendors v ON v.id = vr.vendor_id
             {$where}
             ORDER BY vr.created_at DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM vendor_reviews vr {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        if (($filters['status'] ?? '') === 'pending') {
            return ['WHERE vr.is_approved = 0', []];
        }

        if (($filters['status'] ?? '') === 'approved') {
            return ['WHERE vr.is_approved = 1', []];
        }

        return ['', []];
    }

    public static function approve(int $id): void
    {
        self::update($id, ['is_approved' => 1]);
    }

    public static function approvedForVendor(int $vendorId): array
    {
        $stmt = self::db()->prepare(
            'SELECT vr.*, u.first_name, u.last_name
             FROM vendor_reviews vr
             JOIN users u ON u.id = vr.user_id
             WHERE vr.vendor_id = :vendor_id AND vr.is_approved = 1
             ORDER BY vr.created_at DESC'
        );
        $stmt->execute(['vendor_id' => $vendorId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{count:int,average:float}
     */
    public static function ratingSummary(int $vendorId): array
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS count, COALESCE(AVG(rating), 0) AS average
             FROM vendor_reviews WHERE vendor_id = :vendor_id AND is_approved = 1'
        );
        $stmt->execute(['vendor_id' => $vendorId]);
        $row = $stmt->fetch();

        return ['count' => (int) $row['count'], 'average' => round((float) $row['average'], 1)];
    }

    public static function userHasReviewed(int $vendorId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM vendor_reviews WHERE vendor_id = :vendor_id AND user_id = :user_id'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT vr.*, v.store_name AS vendor_store_name, v.slug AS vendor_slug
             FROM vendor_reviews vr
             JOIN vendors v ON v.id = vr.vendor_id
             WHERE vr.user_id = :user_id
             ORDER BY vr.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function belongsToUser(int $reviewId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM vendor_reviews WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $reviewId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }
}
