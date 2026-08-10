<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Category;
use Tests\Support\Factory;
use Tests\TestCase;

/**
 * activeOrdered() is now cached (Module 29) at a fixed key
 * ('categories.active_ordered') in storage/cache/ - a real file on
 * disk shared with whatever is actually running against this same
 * checkout (the local dev server included), not something
 * Tests\TestCase's per-test DB transaction rollback touches at all.
 * Every test here explicitly invalidates that cache before and after
 * itself, so a category created inside a rolled-back test transaction
 * can never get cached and then survive the rollback as a phantom
 * entry the real app would serve afterward.
 */
final class CategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Category::invalidateCache();
    }

    protected function tearDown(): void
    {
        Category::invalidateCache();
        parent::tearDown();
    }

    public function test_active_ordered_only_returns_active_categories(): void
    {
        $active = Factory::category(['name' => 'Visible Category ' . bin2hex(random_bytes(3)), 'is_active' => 1]);
        $inactive = Factory::category(['name' => 'Hidden Category ' . bin2hex(random_bytes(3)), 'is_active' => 0]);

        $names = array_column(Category::activeOrdered(), 'name');

        $this->assertContains($active['name'], $names);
        $this->assertNotContains($inactive['name'], $names);
    }

    public function test_active_ordered_is_cached_between_calls(): void
    {
        $before = Category::activeOrdered();

        // A row inserted directly (bypassing Category::create(), which
        // would go through the same cached method) must not appear
        // yet - proof the second call served the cached list rather
        // than re-querying the database.
        $category = Factory::category(['name' => 'Not Yet Cached ' . bin2hex(random_bytes(3)), 'is_active' => 1]);

        $after = Category::activeOrdered();

        $this->assertSame(count($before), count($after));
        $this->assertNotContains($category['name'], array_column($after, 'name'));
    }

    public function test_invalidate_cache_makes_the_next_call_see_fresh_data(): void
    {
        Category::activeOrdered();
        $category = Factory::category(['name' => 'Freshly Visible ' . bin2hex(random_bytes(3)), 'is_active' => 1]);

        Category::invalidateCache();

        $names = array_column(Category::activeOrdered(), 'name');
        $this->assertContains($category['name'], $names);
    }
}
