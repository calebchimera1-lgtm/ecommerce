<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Cart\CartCalculator;
use Tests\TestCase;

final class CartCalculatorTest extends TestCase
{
    public function test_subtotal_sums_price_times_quantity_across_items(): void
    {
        $items = [
            ['price' => '50.00', 'quantity' => 2],
            ['price' => '10.00', 'quantity' => 3],
        ];

        $summary = CartCalculator::summarize($items, null, null, null);

        $this->assertSame(130.0, $summary['subtotal']);
        $this->assertSame(0.0, $summary['discount']);
        $this->assertSame(130.0, $summary['total']);
    }

    public function test_percentage_coupon_discounts_the_subtotal(): void
    {
        $items = [['price' => '100.00', 'quantity' => 1]];
        $coupon = ['type' => 'percentage', 'value' => '10', 'min_order_amount' => null, 'max_discount_amount' => null];

        $summary = CartCalculator::summarize($items, $coupon, null, null);

        $this->assertSame(10.0, $summary['discount']);
        $this->assertSame(90.0, $summary['total']);
    }

    public function test_fixed_coupon_never_discounts_more_than_the_subtotal(): void
    {
        $items = [['price' => '20.00', 'quantity' => 1]];
        $coupon = ['type' => 'fixed', 'value' => '50', 'min_order_amount' => null, 'max_discount_amount' => null];

        $summary = CartCalculator::summarize($items, $coupon, null, null);

        $this->assertSame(20.0, $summary['discount'], 'A $50 fixed coupon on a $20 cart should cap at $20.');
        $this->assertSame(0.0, $summary['total']);
    }

    public function test_percentage_coupon_respects_max_discount_cap(): void
    {
        $items = [['price' => '1000.00', 'quantity' => 1]];
        $coupon = ['type' => 'percentage', 'value' => '50', 'min_order_amount' => null, 'max_discount_amount' => '100'];

        $summary = CartCalculator::summarize($items, $coupon, null, null);

        $this->assertSame(100.0, $summary['discount'], '50% of $1000 is $500, but max_discount_amount caps it at $100.');
    }

    public function test_coupon_below_minimum_order_amount_applies_no_discount(): void
    {
        $items = [['price' => '20.00', 'quantity' => 1]];
        $coupon = ['type' => 'fixed', 'value' => '5', 'min_order_amount' => '50', 'max_discount_amount' => null];

        $summary = CartCalculator::summarize($items, $coupon, null, null);

        $this->assertSame(0.0, $summary['discount']);
    }

    public function test_shipping_cost_is_added_from_the_shipping_method(): void
    {
        $items = [['price' => '10.00', 'quantity' => 1]];
        $shippingMethod = ['cost' => '7.50'];

        $summary = CartCalculator::summarize($items, null, $shippingMethod, null);

        $this->assertSame(7.5, $summary['shippingCost']);
        $this->assertSame(17.5, $summary['total']);
    }

    public function test_shipping_is_waived_at_or_above_the_free_shipping_threshold(): void
    {
        // Seeded settings.free_shipping_threshold is 250.00.
        $items = [['price' => '300.00', 'quantity' => 1]];
        $shippingMethod = ['cost' => '15.00'];

        $summary = CartCalculator::summarize($items, null, $shippingMethod, null);

        $this->assertSame(0.0, $summary['shippingCost']);
    }

    public function test_shipping_is_charged_below_the_free_shipping_threshold(): void
    {
        $items = [['price' => '100.00', 'quantity' => 1]];
        $shippingMethod = ['cost' => '15.00'];

        $summary = CartCalculator::summarize($items, null, $shippingMethod, null);

        $this->assertSame(15.0, $summary['shippingCost']);
    }

    public function test_free_shipping_threshold_is_checked_after_discount_is_applied(): void
    {
        // Subtotal is above the $250 threshold, but a 50% coupon drops
        // the post-discount amount below it - shipping should still be
        // charged, since the threshold applies to what's actually owed.
        $items = [['price' => '300.00', 'quantity' => 1]];
        $coupon = ['type' => 'percentage', 'value' => '50', 'min_order_amount' => null, 'max_discount_amount' => null];
        $shippingMethod = ['cost' => '15.00'];

        $summary = CartCalculator::summarize($items, $coupon, $shippingMethod, null);

        $this->assertSame(150.0, $summary['discount']);
        $this->assertSame(15.0, $summary['shippingCost'], 'Post-discount amount ($150) is below the $250 threshold.');
    }

    public function test_tax_is_calculated_on_the_post_discount_amount(): void
    {
        $items = [['price' => '100.00', 'quantity' => 1]];
        $coupon = ['type' => 'fixed', 'value' => '20', 'min_order_amount' => null, 'max_discount_amount' => null];
        $taxRate = ['rate' => '10'];

        $summary = CartCalculator::summarize($items, $coupon, null, $taxRate);

        $this->assertSame(8.0, $summary['taxAmount'], '10% of the post-discount $80, not the original $100.');
        $this->assertSame(88.0, $summary['total']);
    }

    public function test_full_summary_combines_discount_shipping_and_tax(): void
    {
        $items = [
            ['price' => '40.00', 'quantity' => 2],
            ['price' => '20.00', 'quantity' => 1],
        ];
        $coupon = ['type' => 'fixed', 'value' => '10', 'min_order_amount' => null, 'max_discount_amount' => null];
        $shippingMethod = ['cost' => '5.00'];
        $taxRate = ['rate' => '5'];

        $summary = CartCalculator::summarize($items, $coupon, $shippingMethod, $taxRate);

        // subtotal 100, discount 10, after-discount 90, tax 4.5, shipping 5 -> total 99.5
        $this->assertSame(100.0, $summary['subtotal']);
        $this->assertSame(10.0, $summary['discount']);
        $this->assertSame(4.5, $summary['taxAmount']);
        $this->assertSame(5.0, $summary['shippingCost']);
        $this->assertSame(99.5, $summary['total']);
    }
}
