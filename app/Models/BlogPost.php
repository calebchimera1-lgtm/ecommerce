<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;
use PDO;

final class BlogPost extends Model
{
    protected static string $table = 'blog_posts';

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        return self::uniqueSlug(Str::slug($title), $ignoreId);
    }

    /**
     * @param array{search?:string,category_id?:string|int,status?:string} $filters status: 'published'|'draft'|''
     */
    public static function paginateAdmin(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT bp.*, bc.name AS category_name, u.first_name, u.last_name
             FROM blog_posts bp
             LEFT JOIN blog_categories bc ON bc.id = bp.category_id
             JOIN users u ON u.id = bp.author_id
             {$where}
             ORDER BY bp.created_at DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM blog_posts bp {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = 'bp.title LIKE :search';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['category_id'] ?? '') !== '') {
            $conditions[] = 'bp.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['status'] ?? '') === 'published') {
            $conditions[] = 'bp.is_published = 1';
        } elseif (($filters['status'] ?? '') === 'draft') {
            $conditions[] = 'bp.is_published = 0';
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * @param array{category_id?:string|int,search?:string} $filters
     */
    public static function paginatePublic(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildPublicWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug, u.first_name, u.last_name
             FROM blog_posts bp
             LEFT JOIN blog_categories bc ON bc.id = bp.category_id
             JOIN users u ON u.id = bp.author_id
             {$where}
             ORDER BY bp.published_at DESC, bp.created_at DESC
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

    public static function countPublic(array $filters = []): int
    {
        [$where, $bindings] = self::buildPublicWhere($filters);
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM blog_posts bp {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildPublicWhere(array $filters): array
    {
        $conditions = ['bp.is_published = 1', '(bp.published_at IS NULL OR bp.published_at <= NOW())'];
        $bindings = [];

        if (($filters['category_id'] ?? '') !== '') {
            $conditions[] = 'bp.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(bp.title LIKE :search_title OR bp.excerpt LIKE :search_excerpt)';
            $bindings['search_title'] = '%' . $filters['search'] . '%';
            $bindings['search_excerpt'] = '%' . $filters['search'] . '%';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug, u.first_name, u.last_name
             FROM blog_posts bp
             LEFT JOIN blog_categories bc ON bc.id = bp.category_id
             JOIN users u ON u.id = bp.author_id
             WHERE bp.slug = :slug AND bp.is_published = 1 AND (bp.published_at IS NULL OR bp.published_at <= NOW())
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT bp.*, bc.name AS category_name, u.first_name, u.last_name
             FROM blog_posts bp
             LEFT JOIN blog_categories bc ON bc.id = bp.category_id
             JOIN users u ON u.id = bp.author_id
             WHERE bp.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Recently published posts excluding one (the post currently being
     * viewed) - feeds the blog post show page's "More Articles" rail.
     */
    public static function recentPublishedExcluding(int $excludeId, int $limit = 3): array
    {
        $stmt = self::db()->prepare(
            "SELECT bp.* FROM blog_posts bp
             WHERE bp.is_published = 1 AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                AND bp.id != :exclude_id
             ORDER BY bp.published_at DESC, bp.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Every published post's slug and last-modified timestamp - the
     * sitemap's data source (deliberately minimal columns, since a
     * sitemap doesn't need post content).
     */
    public static function allPublishedForSitemap(): array
    {
        $stmt = self::db()->query(
            "SELECT slug, updated_at FROM blog_posts
             WHERE is_published = 1 AND (published_at IS NULL OR published_at <= NOW())"
        );

        return $stmt->fetchAll();
    }
}
