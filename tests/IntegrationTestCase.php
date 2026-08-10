<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base for tests that exercise code owning its own transaction
 * (OrderPlacementService's beginTransaction()/commit() around order
 * placement is the main example) - Tests\TestCase's outer-transaction
 * isolation would collide with that (PDO/MySQL don't support real
 * nested transactions), so this isolates differently: truncating the
 * order-related tables before each test instead of wrapping one.
 *
 * Fixture rows created via Tests\Support\Factory (users, vendors,
 * categories, products) are NOT truncated here - each Factory call
 * generates unique values (random SKU, sequential email/slug), so
 * they don't collide across tests and are cheap enough to just
 * accumulate for the life of a test run.
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    protected PDO $db;

    private const TRUNCATE_TABLES = [
        'return_request_status_history',
        'return_request_items',
        'return_requests',
        'vendor_order_status_history',
        'order_status_history',
        'payments',
        'order_items',
        'vendor_orders',
        'order_addresses',
        'orders',
        'coupon_usages',
        'cart_items',
        'carts',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connection();
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TRUNCATE_TABLES as $table) {
            $this->db->exec("TRUNCATE TABLE `{$table}`");
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
