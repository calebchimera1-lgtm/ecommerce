<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Uuid;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;

/**
 * Minimal fixture builders for tests - not a full factory framework,
 * just enough to create a valid customer/vendor/category/product row
 * without every test re-deriving the same required-column boilerplate.
 * Every method returns the freshly-created row (via Model::find()) so
 * assertions can read real column defaults rather than assuming them.
 *
 * Unique fields (email, slug) use a random token, not just an
 * incrementing counter - Tests\IntegrationTestCase deliberately never
 * truncates fixture tables (users/vendors/categories/products), so
 * their rows persist in the test database across separate `phpunit`
 * invocations. A counter alone restarts at 1 every process and would
 * collide with a prior run's leftover row; a random token doesn't.
 */
final class Factory
{
    private static int $sequence = 0;

    private static function token(): string
    {
        return ++self::$sequence . '-' . bin2hex(random_bytes(4));
    }

    public static function customer(array $overrides = []): array
    {
        $token = self::token();
        $role = Role::findBy('slug', 'customer');

        $id = User::create(array_merge([
            'uuid' => Uuid::v4(),
            'role_id' => $role['id'],
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => "customer-{$token}@example.test",
            'password_hash' => password_hash('TestPass!2024', PASSWORD_ARGON2ID),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ], $overrides));

        return User::find($id);
    }

    /**
     * @return array{user:array,vendor:array}
     */
    public static function vendor(string $status = 'approved', array $overrides = []): array
    {
        $token = self::token();
        $role = Role::findBy('slug', 'vendor');

        $userId = User::create([
            'uuid' => Uuid::v4(),
            'role_id' => $role['id'],
            'first_name' => 'Test',
            'last_name' => 'Vendor',
            'email' => "vendor-{$token}@example.test",
            'password_hash' => password_hash('TestPass!2024', PASSWORD_ARGON2ID),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $storeName = 'Test Store ' . $token;
        $vendorId = Vendor::create(array_merge([
            'user_id' => $userId,
            'store_name' => $storeName,
            'slug' => Vendor::generateSlug($storeName),
            'status' => $status,
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ], $overrides));

        return ['user' => User::find($userId), 'vendor' => Vendor::find($vendorId)];
    }

    public static function category(array $overrides = []): array
    {
        $token = self::token();

        $id = Category::create(array_merge([
            'name' => 'Test Category ' . $token,
            'slug' => 'test-category-' . $token,
            'is_active' => 1,
            'sort_order' => 0,
        ], $overrides));

        return Category::find($id);
    }

    /**
     * @param int|null $vendorId Pass null (default) for a platform-owned product, or a vendors.id for a vendor listing.
     */
    public static function product(?int $categoryId = null, ?int $vendorId = null, array $overrides = []): array
    {
        $token = self::token();
        $categoryId ??= self::category()['id'];

        $id = Product::create(array_merge([
            'sku' => 'TEST-' . strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Test Product ' . $token,
            'slug' => Product::generateSlug('Test Product ' . $token),
            'category_id' => $categoryId,
            'vendor_id' => $vendorId,
            'price' => '99.00',
            'stock_quantity' => 10,
            'is_active' => 1,
            'approval_status' => $vendorId !== null ? 'pending' : 'approved',
        ], $overrides));

        return Product::find($id);
    }
}
