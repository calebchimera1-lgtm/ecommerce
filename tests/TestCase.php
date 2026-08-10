<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base for tests that only need simple CRUD-style DB access (model
 * unit tests, validator tests, calculator tests). Wraps every test in
 * a transaction that's rolled back in tearDown(), so tests never leak
 * rows into each other and never need manual cleanup.
 *
 * Do NOT extend this for anything that exercises code with its own
 * internal transaction (e.g. OrderPlacementService, which calls
 * $db->beginTransaction()/commit() itself) - PDO/MySQL don't support
 * real nested transactions, so a second beginTransaction() while this
 * class's own transaction is open would throw. Use
 * Tests\IntegrationTestCase for that instead.
 */
abstract class TestCase extends BaseTestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }
}
