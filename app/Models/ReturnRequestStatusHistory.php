<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ReturnRequestStatusHistory extends Model
{
    protected static string $table = 'return_request_status_history';

    public static function forReturnRequest(int $returnRequestId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM return_request_status_history WHERE return_request_id = :return_request_id ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['return_request_id' => $returnRequestId]);

        return $stmt->fetchAll();
    }
}
