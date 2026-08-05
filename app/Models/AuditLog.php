<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    public static function record(
        ?int $userId,
        string $action,
        ?string $model = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        self::create([
            'user_id' => $userId,
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    }

    public static function paginateAll(int $page, int $perPage): array
    {
        $offset = (max(1, $page) - 1) * $perPage;
        $stmt = self::db()->prepare(
            'SELECT al.*, u.first_name, u.last_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
