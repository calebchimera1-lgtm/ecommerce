<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Setting;

/**
 * Pure calculation of a cart's totals - no persistence, no side
 * effects - so it can be reused unchanged by the checkout flow once
 * Module 7 exists.
 */
final class CartCalculator
{
    /**
     * @param array $items Rows from CartItem::forCart() (must have 'price' and 'quantity').
     * @param array|null $coupon Row from Coupon::findValidByCode(), or null.
     * @param array|null $shippingMethod Row from ShippingMethod, or null.
     * @param array|null $taxRate Row from TaxRate, or null.
     * @return array{subtotal:float,discount:float,shippingCost:float,taxAmount:float,total:float}
     */
    public static function summarize(array $items, ?array $coupon, ?array $shippingMethod, ?array $taxRate): array
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }

        $discount = $coupon !== null ? self::calculateDiscount($subtotal, $coupon) : 0.0;
        $afterDiscount = max(0.0, $subtotal - $discount);

        $shippingCost = $shippingMethod !== null ? (float) $shippingMethod['cost'] : 0.0;
        $freeShippingThreshold = Setting::get('free_shipping_threshold');

        if ($freeShippingThreshold !== null && $afterDiscount >= (float) $freeShippingThreshold) {
            $shippingCost = 0.0;
        }

        $taxAmount = $taxRate !== null ? round($afterDiscount * ((float) $taxRate['rate'] / 100), 2) : 0.0;
        $total = $afterDiscount + $shippingCost + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shippingCost' => $shippingCost,
            'taxAmount' => $taxAmount,
            'total' => $total,
        ];
    }

    private static function calculateDiscount(float $subtotal, array $coupon): float
    {
        if ($coupon['min_order_amount'] !== null && $subtotal < (float) $coupon['min_order_amount']) {
            return 0.0;
        }

        $discount = $coupon['type'] === 'percentage'
            ? $subtotal * ((float) $coupon['value'] / 100)
            : (float) $coupon['value'];

        if ($coupon['max_discount_amount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount_amount']);
        }

        return min($discount, $subtotal);
    }
}
