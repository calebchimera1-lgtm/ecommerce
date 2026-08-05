<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class ProductReview extends Model
{
    protected static string $table = 'product_reviews';

    /**
     * @param array{status?:string} $filters status: 'pending'|'approved'|''
     */
    public static function paginateAdmin(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT pr.*, u.first_name, u.last_name, p.name AS product_name, p.slug AS product_slug
             FROM product_reviews pr
             JOIN users u ON u.id = pr.user_id
             JOIN products p ON p.id = pr.product_id
             {$where}
             ORDER BY pr.created_at DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM product_reviews pr {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        if (($filters['status'] ?? '') === 'pending') {
            return ['WHERE pr.is_approved = 0', []];
        }

        if (($filters['status'] ?? '') === 'approved') {
            return ['WHERE pr.is_approved = 1', []];
        }

        return ['', []];
    }

    public static function approve(int $id): void
    {
        self::update($id, ['is_approved' => 1]);
    }

    public static function approvedForProduct(int $productId): array
    {
        $stmt = self::db()->prepare(
            'SELECT pr.*, u.first_name, u.last_name
             FROM product_reviews pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.product_id = :product_id AND pr.is_approved = 1
             ORDER BY pr.created_at DESC'
        );
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{count:int,average:float}
     */
    public static function ratingSummary(int $productId): array
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS count, COALESCE(AVG(rating), 0) AS average
             FROM product_reviews WHERE product_id = :product_id AND is_approved = 1'
        );
        $stmt->execute(['product_id' => $productId]);
        $row = $stmt->fetch();

        return ['count' => (int) $row['count'], 'average' => round((float) $row['average'], 1)];
    }

    public static function userHasReviewed(int $productId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM product_reviews WHERE product_id = :product_id AND user_id = :user_id'
        );
        $stmt->execute(['product_id' => $productId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT pr.*, p.name AS product_name, p.slug AS product_slug
             FROM product_reviews pr
             JOIN products p ON p.id = pr.product_id
             WHERE pr.user_id = :user_id
             ORDER BY pr.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function belongsToUser(int $reviewId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS total FROM product_reviews WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $reviewId, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }
}
