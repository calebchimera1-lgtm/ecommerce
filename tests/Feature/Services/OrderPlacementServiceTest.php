<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorOrder;
use App\Services\Order\OrderPlacementService;
use Tests\IntegrationTestCase;
use Tests\Support\Factory;

/**
 * Covers the order-splitting/commission math added in Module 18 -
 * the highest-risk logic in the app (real money, cross-vendor data
 * isolation) and the reason this class exists as an IntegrationTestCase
 * rather than a transaction-wrapped unit test: OrderPlacementService
 * owns its own DB transaction, so these tests need real commits to
 * observe its actual behavior.
 */
final class OrderPlacementServiceTest extends IntegrationTestCase
{
    private array $address = [
        'full_name' => 'Jane Doe',
        'phone' => '+15551234567',
        'address_line1' => '123 Market St',
        'address_line2' => null,
        'city' => 'San Francisco',
        'state' => 'CA',
        'postal_code' => '94103',
        'country' => 'United States',
    ];

    private function addToCart(int $cartId, array $product, int $quantity): void
    {
        CartItem::create([
            'cart_id' => $cartId,
            'product_id' => $product['id'],
            'product_attribute_id' => null,
            'quantity' => $quantity,
            'price' => $product['sale_price'] ?? $product['price'],
        ]);
    }

    public function test_a_cart_with_only_platform_products_creates_no_vendor_orders(): void
    {
        $customer = Factory::customer();
        $category = Factory::category();
        $product = Factory::product((int) $category['id'], null, ['price' => '50.00']);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $product, 2);
        $items = CartItem::forCart($cartId);

        $order = OrderPlacementService::place(
            (int) $customer['id'],
            $cartId,
            $items,
            $this->address,
            $this->address,
            null,
            null,
            null,
            'cod',
            []
        );

        $this->assertSame('100.00', $order['subtotal']);

        $orderItems = OrderItem::forOrder((int) $order['id']);
        $this->assertCount(1, $orderItems);
        $this->assertNull($orderItems[0]['vendor_order_id']);
        $this->assertNull($orderItems[0]['commission_rate']);

        $this->assertCount(0, VendorOrder::forOrder((int) $order['id']));
    }

    public function test_a_mixed_cart_splits_into_one_vendor_order_per_vendor_with_correct_commission(): void
    {
        $customer = Factory::customer();
        $category = Factory::category(['commission_rate' => null]); // falls back to the platform default
        ['vendor' => $vendor] = Factory::vendor('approved');

        $platformProduct = Factory::product((int) $category['id'], null, ['price' => '40.00']);
        $vendorProduct = Factory::product((int) $category['id'], (int) $vendor['id'], [
            'price' => '100.00',
            'approval_status' => 'approved',
        ]);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $platformProduct, 1);
        $this->addToCart($cartId, $vendorProduct, 2); // subtotal 200.00
        $items = CartItem::forCart($cartId);

        $order = OrderPlacementService::place(
            (int) $customer['id'],
            $cartId,
            $items,
            $this->address,
            $this->address,
            null,
            null,
            null,
            'cod',
            []
        );

        $this->assertSame('240.00', $order['subtotal']); // 40 + 200

        $vendorOrders = VendorOrder::forOrder((int) $order['id']);
        $this->assertCount(1, $vendorOrders, 'Only the vendor-owned line should produce a vendor_orders row.');

        $vendorOrder = $vendorOrders[0];
        $this->assertSame((int) $vendor['id'], (int) $vendorOrder['vendor_id']);
        $this->assertSame('200.00', $vendorOrder['subtotal']);
        $this->assertSame('pending', $vendorOrder['status']);
        $this->assertSame('unpaid', $vendorOrder['payout_status']);

        // Default commission rate (seeded settings.default_commission_rate = 15.00):
        // commission = 200.00 * 15% = 30.00; payout = 200.00 - 30.00 = 170.00
        $this->assertSame('30.00', $vendorOrder['commission_amount']);
        $this->assertSame('170.00', $vendorOrder['payout_amount']);

        $orderItems = OrderItem::forOrder((int) $order['id']);
        $this->assertCount(2, $orderItems);

        $platformLine = self::findLineFor($orderItems, (int) $platformProduct['id']);
        $vendorLine = self::findLineFor($orderItems, (int) $vendorProduct['id']);

        $this->assertNull($platformLine['vendor_order_id']);
        $this->assertNull($platformLine['commission_amount']);

        $this->assertSame((int) $vendorOrder['id'], (int) $vendorLine['vendor_order_id']);
        $this->assertSame('15.00', $vendorLine['commission_rate']);
        $this->assertSame('30.00', $vendorLine['commission_amount']);
    }

    public function test_commission_uses_the_categorys_own_rate_when_set(): void
    {
        $customer = Factory::customer();
        $category = Factory::category(['commission_rate' => '25.00']);
        ['vendor' => $vendor] = Factory::vendor('approved');
        $product = Factory::product((int) $category['id'], (int) $vendor['id'], ['price' => '100.00']);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $product, 1);
        $items = CartItem::forCart($cartId);

        $order = OrderPlacementService::place(
            (int) $customer['id'], $cartId, $items, $this->address, $this->address, null, null, null, 'cod', []
        );

        $vendorOrder = VendorOrder::forOrder((int) $order['id'])[0];

        // 25% of 100.00 = 25.00, not the 15% platform default.
        $this->assertSame('25.00', $vendorOrder['commission_amount']);
        $this->assertSame('75.00', $vendorOrder['payout_amount']);
    }

    public function test_two_different_vendors_in_one_cart_each_get_their_own_vendor_order(): void
    {
        $customer = Factory::customer();
        $category = Factory::category();
        ['vendor' => $vendorA] = Factory::vendor('approved');
        ['vendor' => $vendorB] = Factory::vendor('approved');

        $productA = Factory::product((int) $category['id'], (int) $vendorA['id'], ['price' => '60.00']);
        $productB = Factory::product((int) $category['id'], (int) $vendorB['id'], ['price' => '40.00']);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $productA, 1);
        $this->addToCart($cartId, $productB, 1);
        $items = CartItem::forCart($cartId);

        $order = OrderPlacementService::place(
            (int) $customer['id'], $cartId, $items, $this->address, $this->address, null, null, null, 'cod', []
        );

        $vendorOrders = VendorOrder::forOrder((int) $order['id']);
        $this->assertCount(2, $vendorOrders);

        $vendorIds = array_map(static fn (array $vo): int => (int) $vo['vendor_id'], $vendorOrders);
        $this->assertContains((int) $vendorA['id'], $vendorIds);
        $this->assertContains((int) $vendorB['id'], $vendorIds);
    }

    public function test_stock_is_decremented_and_the_cart_is_cleared(): void
    {
        $customer = Factory::customer();
        $category = Factory::category();
        $product = Factory::product((int) $category['id'], null, ['price' => '20.00', 'stock_quantity' => 10]);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $product, 3);
        $items = CartItem::forCart($cartId);

        OrderPlacementService::place(
            (int) $customer['id'], $cartId, $items, $this->address, $this->address, null, null, null, 'cod', []
        );

        $this->assertSame(7, (int) Product::find((int) $product['id'])['stock_quantity']);
        $this->assertCount(0, CartItem::forCart($cartId), 'Cart items must be cleared after successful placement.');
    }

    public function test_insufficient_stock_rejects_the_order_and_creates_nothing(): void
    {
        $customer = Factory::customer();
        $category = Factory::category();
        $product = Factory::product((int) $category['id'], null, ['price' => '20.00', 'stock_quantity' => 1]);

        $cartId = Cart::create(['user_id' => $customer['id']]);
        $this->addToCart($cartId, $product, 5);
        $items = CartItem::forCart($cartId);

        $this->expectException(\App\Services\Order\OrderPlacementException::class);

        try {
            OrderPlacementService::place(
                (int) $customer['id'], $cartId, $items, $this->address, $this->address, null, null, null, 'cod', []
            );
        } finally {
            // Even on failure, stock and the cart must be untouched -
            // the whole placement is one atomic transaction.
            $this->assertSame(1, (int) Product::find((int) $product['id'])['stock_quantity']);
            $this->assertCount(1, CartItem::forCart($cartId));
        }
    }

    private static function findLineFor(array $orderItems, int $productId): array
    {
        foreach ($orderItems as $item) {
            if ((int) $item['product_id'] === $productId) {
                return $item;
            }
        }

        throw new \RuntimeException("No order_items row for product {$productId}");
    }
}
