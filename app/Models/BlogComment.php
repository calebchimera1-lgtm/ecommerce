<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class BlogComment extends Model
{
    protected static string $table = 'blog_comments';

    public static function approvedForPost(int $postId): array
    {
        $stmt = self::db()->prepare(
            'SELECT bc.*, u.first_name, u.last_name
             FROM blog_comments bc
             JOIN users u ON u.id = bc.user_id
             WHERE bc.post_id = :post_id AND bc.is_approved = 1
             ORDER BY bc.created_at ASC'
        );
        $stmt->execute(['post_id' => $postId]);

        return $stmt->fetchAll();
    }

    /**
     * All comments (approved and pending) for a post - the admin post
     * editor's moderation section shows both so a moderator can see
     * what's awaiting review without leaving the post they're editing.
     */
    public static function forPostAdmin(int $postId): array
    {
        $stmt = self::db()->prepare(
            'SELECT bc.*, u.first_name, u.last_name
             FROM blog_comments bc
             JOIN users u ON u.id = bc.user_id
             WHERE bc.post_id = :post_id
             ORDER BY bc.created_at DESC'
        );
        $stmt->execute(['post_id' => $postId]);

        return $stmt->fetchAll();
    }

    public static function approve(int $id): void
    {
        self::update($id, ['is_approved' => 1]);
    }
}
