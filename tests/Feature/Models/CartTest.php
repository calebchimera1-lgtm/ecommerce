<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Core\Database;
use App\Models\Cart;
use App\Models\CartItem;
use Tests\Support\Factory;
use Tests\TestCase;

final class CartTest extends TestCase
{
    private function touch(string $table, int $id, string $when): void
    {
        $stmt = Database::connection()->prepare("UPDATE {$table} SET updated_at = :when WHERE id = :id");
        $stmt->execute(['when' => $when, 'id' => $id]);
    }

    private function makeCartWithItem(int $userId, string $touchedAgo): int
    {
        $category = Factory::category();
        $product = Factory::product((int) $category['id']);
        $cartId = Cart::create(['user_id' => $userId]);
        $itemId = CartItem::create([
            'cart_id' => $cartId, 'product_id' => $product['id'], 'product_attribute_id' => null,
            'quantity' => 1, 'price' => $product['price'],
        ]);

        $when = date('Y-m-d H:i:s', strtotime($touchedAgo));
        $this->touch('carts', $cartId, $when);
        $this->touch('cart_items', $itemId, $when);

        return $cartId;
    }

    public function test_a_cart_older_than_the_threshold_is_abandoned(): void
    {
        $customer = Factory::customer();
        $this->makeCartWithItem((int) $customer['id'], '-30 hours');

        $abandoned = Cart::abandoned(24);

        $this->assertNotEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['user_id'] === (int) $customer['id']));
    }

    public function test_a_cart_touched_within_the_threshold_is_not_abandoned(): void
    {
        $customer = Factory::customer();
        $this->makeCartWithItem((int) $customer['id'], '-1 hour');

        $abandoned = Cart::abandoned(24);

        $this->assertEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['user_id'] === (int) $customer['id']));
    }

    public function test_an_empty_cart_is_never_abandoned(): void
    {
        $customer = Factory::customer();
        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->touch('carts', $cartId, date('Y-m-d H:i:s', strtotime('-30 hours')));

        $abandoned = Cart::abandoned(24);

        $this->assertEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['id'] === $cartId));
    }

    public function test_a_guest_cart_with_no_user_is_never_abandoned(): void
    {
        $cartId = Cart::create(['user_id' => null, 'session_id' => 'guest-' . bin2hex(random_bytes(4))]);
        $category = Factory::category();
        $product = Factory::product((int) $category['id']);
        CartItem::create([
            'cart_id' => $cartId, 'product_id' => $product['id'], 'product_attribute_id' => null,
            'quantity' => 1, 'price' => $product['price'],
        ]);
        $this->touch('carts', $cartId, date('Y-m-d H:i:s', strtotime('-30 hours')));

        $abandoned = Cart::abandoned(24);

        $this->assertEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['id'] === $cartId));
    }

    public function test_a_cart_already_reminded_since_its_last_touch_is_not_reincluded(): void
    {
        $customer = Factory::customer();
        $cartId = $this->makeCartWithItem((int) $customer['id'], '-30 hours');
        Cart::markReminderSent($cartId);

        $abandoned = Cart::abandoned(24);

        $this->assertEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['id'] === $cartId));
    }

    public function test_a_cart_touched_again_after_a_reminder_becomes_eligible_once_idle_again(): void
    {
        $customer = Factory::customer();
        $cartId = $this->makeCartWithItem((int) $customer['id'], '-72 hours');

        // Reminder sent 48h ago; customer came back and touched the
        // cart 30h ago (after that reminder), then went idle again.
        // Cart::update() must run before the touch() calls below - it
        // doesn't set updated_at itself, so MySQL's own ON UPDATE
        // CURRENT_TIMESTAMP would otherwise bump it to "now" and undo
        // the backdating.
        Cart::update($cartId, ['reminder_sent_at' => date('Y-m-d H:i:s', strtotime('-48 hours'))]);
        $this->touch('carts', $cartId, date('Y-m-d H:i:s', strtotime('-30 hours')));

        $stmt = Database::connection()->prepare('SELECT id FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cartId]);
        $itemId = (int) $stmt->fetch()['id'];
        $this->touch('cart_items', $itemId, date('Y-m-d H:i:s', strtotime('-30 hours')));

        $abandoned = Cart::abandoned(24);

        $this->assertNotEmpty(array_filter($abandoned, static fn (array $c): bool => (int) $c['id'] === $cartId));
    }
}
