<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Setting extends Model
{
    protected static string $table = 'settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::findBy('setting_key', $key);

        return $row !== null ? $row['value'] : $default;
    }
}
