<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;

/**
 * A vendor's business profile - one row per vendor user account
 * (`vendors.user_id` is unique). The account's login identity and
 * password live on `users` like every other account type; this table
 * holds only what's specific to being a seller: store name/slug,
 * approval status, and payout details.
 *
 * This module ships the foundation (schema, auth, a placeholder
 * dashboard) - self-service registration and the admin approval queue
 * are Module 16's job, the same way Module 3 shipped admin auth with
 * a placeholder dashboard before Module 9 built the real one.
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
}
