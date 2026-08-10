<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * A customer's request to return specific items from a delivered
 * order. Scoped per order_item (via ReturnRequestItem), not the whole
 * order - a request can cover just one line out of a larger order.
 * Moderation mirrors every other approval queue in this codebase
 * (product listings, vendor applications, reviews): pending ->
 * approved/rejected, with a status history trail
 * (ReturnRequestStatusHistory) recording who changed what and when.
 */
final class ReturnRequest extends Model
{
    protected static string $table = 'return_requests';

    public static function findWithDetails(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT rr.*, o.order_number, o.total AS order_total, u.first_name, u.last_name, u.email
             FROM return_requests rr
             JOIN orders o ON o.id = rr.order_id
             JOIN users u ON u.id = rr.user_id
             WHERE rr.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function belongsToUser(int $id, int $userId): bool
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS total FROM return_requests WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return (int) $stmt->fetch()['total'] > 0;
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT rr.*, o.order_number
             FROM return_requests rr
             JOIN orders o ON o.id = rr.order_id
             WHERE rr.user_id = :user_id
             ORDER BY rr.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{status?:string} $filters
     */
    public static function paginateAdmin(int $page, int $perPage, array $filters = []): array
    {
        [$where, $bindings] = self::buildAdminFilterWhere($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT rr.*, o.order_number, u.first_name, u.last_name, u.email
             FROM return_requests rr
             JOIN orders o ON o.id = rr.order_id
             JOIN users u ON u.id = rr.user_id
             {$where}
             ORDER BY rr.created_at DESC
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
        $stmt = self::db()->prepare("SELECT COUNT(*) AS total FROM return_requests rr {$where}");
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    private static function buildAdminFilterWhere(array $filters): array
    {
        if (($filters['status'] ?? '') !== '') {
            return ['WHERE rr.status = :status', ['status' => $filters['status']]];
        }

        return ['', []];
    }

    public static function updateStatus(int $id, string $status, ?string $note, ?int $changedBy): void
    {
        self::update($id, ['status' => $status]);

        ReturnRequestStatusHistory::create([
            'return_request_id' => $id,
            'status' => $status,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }

    public static function approve(int $id, int $adminId, ?string $note): void
    {
        self::update($id, ['status' => 'approved', 'admin_note' => $note, 'processed_by' => $adminId]);
        ReturnRequestStatusHistory::create([
            'return_request_id' => $id, 'status' => 'approved', 'note' => $note, 'changed_by' => $adminId,
        ]);
    }

    public static function reject(int $id, int $adminId, string $reason): void
    {
        self::update($id, ['status' => 'rejected', 'admin_note' => $reason, 'processed_by' => $adminId]);
        ReturnRequestStatusHistory::create([
            'return_request_id' => $id, 'status' => 'rejected', 'note' => $reason, 'changed_by' => $adminId,
        ]);
    }

    public static function markRefunded(int $id, int $adminId, string $refundedAmount): void
    {
        self::update($id, [
            'status' => 'refunded',
            'refunded_amount' => $refundedAmount,
            'refunded_at' => date('Y-m-d H:i:s'),
            'processed_by' => $adminId,
        ]);
        ReturnRequestStatusHistory::create([
            'return_request_id' => $id,
            'status' => 'refunded',
            'note' => 'Refunded ' . $refundedAmount,
            'changed_by' => $adminId,
        ]);
    }
}
