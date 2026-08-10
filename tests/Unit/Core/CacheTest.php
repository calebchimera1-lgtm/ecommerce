<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Cache;
use PHPUnit\Framework\TestCase;

/**
 * Cache is filesystem-based (storage/cache/*.cache), not a separate
 * per-environment store the way the database is (see kymera_collection_test) -
 * these tests use uniquely-namespaced keys and clean up after
 * themselves with forget(), the same tolerance ImageUploaderTest
 * already has for touching real files under storage/ (regenerable
 * runtime state, not meaningful seeded data).
 */
final class CacheTest extends TestCase
{
    private array $keysToForget = [];

    protected function tearDown(): void
    {
        foreach ($this->keysToForget as $key) {
            Cache::forget($key);
        }

        parent::tearDown();
    }

    private function key(string $suffix): string
    {
        $key = 'test.cache.' . $suffix . '.' . bin2hex(random_bytes(4));
        $this->keysToForget[] = $key;

        return $key;
    }

    public function test_remember_only_calls_the_callback_once_for_a_fresh_key(): void
    {
        $key = $this->key('once');
        $calls = 0;
        $callback = function () use (&$calls): string {
            $calls++;

            return 'computed';
        };

        $first = Cache::remember($key, 60, $callback);
        $second = Cache::remember($key, 60, $callback);

        $this->assertSame('computed', $first);
        $this->assertSame('computed', $second);
        $this->assertSame(1, $calls, 'The callback must not run again while the cached value is still fresh.');
    }

    public function test_remember_preserves_the_actual_value_type(): void
    {
        $key = $this->key('array');
        $value = ['a' => 1, 'b' => ['nested' => true]];

        $result = Cache::remember($key, 60, static fn (): array => $value);

        $this->assertSame($value, $result);
    }

    public function test_forget_makes_the_next_remember_call_recompute(): void
    {
        $key = $this->key('forget');
        $calls = 0;
        $callback = function () use (&$calls): int {
            $calls++;

            return $calls;
        };

        Cache::remember($key, 60, $callback);
        Cache::forget($key);
        Cache::remember($key, 60, $callback);

        $this->assertSame(2, $calls);
    }

    public function test_an_expired_entry_is_recomputed_not_served_stale(): void
    {
        $key = $this->key('expired');
        Cache::put($key, 'old-value', -1); // already expired

        $result = Cache::remember($key, 60, static fn (): string => 'new-value');

        $this->assertSame('new-value', $result);
    }

    public function test_forgetting_an_unknown_key_does_not_error(): void
    {
        Cache::forget('test.cache.never-existed.' . bin2hex(random_bytes(4)));

        $this->addToAssertionCount(1);
    }

    public function test_put_then_remember_returns_the_put_value_without_invoking_the_callback(): void
    {
        $key = $this->key('put');
        Cache::put($key, 'preloaded', 60);

        $called = false;
        $result = Cache::remember($key, 60, function () use (&$called): string {
            $called = true;

            return 'should-not-run';
        });

        $this->assertSame('preloaded', $result);
        $this->assertFalse($called);
    }
}
