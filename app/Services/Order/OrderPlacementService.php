<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Core\Database;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\Cart\CartCalculator;
use App\Services\Payment\PaymentGatewayManager;
use Throwable;

/**
 * Turns a cart into an order, atomically. On any failure - insufficient
 * stock discovered at the last moment, or the payment gateway
 * declining - the whole transaction rolls back: no order, no stock
 * decrement, no cleared cart. The customer sees why and can retry.
 * This is simpler and safer than the alternative (creating a
 * payment-failed order and building a separate retry flow), and is a
 * legitimate pattern in its own right, not just an expedient one.
 */
final class OrderPlacementService
{
    /**
     * @param array $cartItems Rows from CartItem::forCart().
     * @param array $billingAddress Keys matching order_addresses columns (full_name, phone, address_line1, address_line2, city, state, postal_code, country).
     * @param array $shippingAddress Same shape as $billingAddress.
     * @param array<string,mixed> $paymentInput Gateway-specific input (card token, phone number, ...).
     */
    public static function place(
        int $userId,
        int $cartId,
        array $cartItems,
        array $billingAddress,
        array $shippingAddress,
        ?array $coupon,
        ?array $shippingMethod,
        ?array $taxRate,
        string $paymentGatewaySlug,
        array $paymentInput
    ): array {
        if ($cartItems === []) {
            throw new OrderPlacementException('Your cart is empty.');
        }

        self::assertStockAvailable($cartItems);
        self::assertCouponStillValid($cartItems, $coupon, $userId);

        $summary = CartCalculator::summarize($cartItems, $coupon, $shippingMethod, $taxRate);
        $gateway = PaymentGatewayManager::resolve($paymentGatewaySlug);

        if (!$gateway->isConfigured()) {
            throw new OrderPlacementException($gateway->label() . ' is not currently available. Please choose a different payment method.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $orderId = Order::create([
                'order_number' => 'TEMP-' . bin2hex(random_bytes(8)),
                'user_id' => $userId,
                'status' => 'pending',
                'subtotal' => (string) $summary['subtotal'],
                'discount_amount' => (string) $summary['discount'],
                'coupon_id' => $coupon['id'] ?? null,
                'shipping_method_id' => $shippingMethod['id'] ?? null,
                'shipping_amount' => (string) $summary['shippingCost'],
                'tax_amount' => (string) $summary['taxAmount'],
                'total' => (string) $summary['total'],
                'currency' => 'USD',
                'payment_method' => $paymentGatewaySlug,
                'payment_status' => 'unpaid',
            ]);

            $orderNumber = 'KYM-' . date('Y') . '-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            Order::update($orderId, ['order_number' => $orderNumber]);

            OrderAddress::create(array_merge($billingAddress, ['order_id' => $orderId, 'type' => 'billing']));
            OrderAddress::create(array_merge($shippingAddress, ['order_id' => $orderId, 'type' => 'shipping']));

            foreach ($cartItems as $item) {
                $sku = $item['product_sku'] . ($item['sku_suffix'] !== null ? '-' . $item['sku_suffix'] : '');

                OrderItem::create([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_attribute_id' => $item['product_attribute_id'],
                    'product_name' => $item['product_name'],
                    'sku' => $sku,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => (string) ((float) $item['price'] * (int) $item['quantity']),
                ]);

                self::decrementStock($item, $orderId);
            }

            OrderStatusHistory::create([
                'order_id' => $orderId,
                'status' => 'pending',
                'note' => 'Order placed.',
                'changed_by' => null,
            ]);

            $result = $gateway->charge(
                ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $summary['total']],
                $paymentInput
            );

            Payment::create([
                'order_id' => $orderId,
                'gateway' => $paymentGatewaySlug,
                'transaction_id' => $result->transactionId,
                'amount' => (string) $summary['total'],
                'currency' => 'USD',
                'status' => $result->status,
                'payload' => $result->raw !== [] ? json_encode($result->raw, JSON_UNESCAPED_SLASHES) : null,
                'paid_at' => $result->status === 'completed' ? date('Y-m-d H:i:s') : null,
            ]);

            if (!$result->success) {
                throw new OrderPlacementException($result->message ?? 'Payment could not be completed.');
            }

            if ($result->status === 'completed') {
                Order::update($orderId, ['payment_status' => 'paid']);
            }

            if ($coupon !== null) {
                CouponUsage::create(['coupon_id' => $coupon['id'], 'user_id' => $userId, 'order_id' => $orderId]);
                Coupon::incrementUsage((int) $coupon['id']);
            }

            CartItem::clearForCart($cartId);
            Cart::applyCoupon($cartId, null);
            Cart::setShippingMethod($cartId, null);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();

            throw $e instanceof OrderPlacementException
                ? $e
                : new OrderPlacementException('We could not process your order. Please try again.', previous: $e);
        }

        return Order::find($orderId);
    }

    private static function assertStockAvailable(array $cartItems): void
    {
        foreach ($cartItems as $item) {
            $available = $item['product_attribute_id'] !== null
                ? (int) $item['attribute_stock']
                : (int) $item['product_stock'];

            if ((int) $item['quantity'] > $available) {
                throw new OrderPlacementException(
                    "Sorry, \"{$item['product_name']}\" only has {$available} left in stock."
                );
            }
        }
    }

    /**
     * Re-validates a coupon that was already applied to the cart,
     * against its current DB state, right before it's used to compute
     * the order total. Without this, a coupon applied at cart-add time
     * (CartController::applyCoupon(), which does check all of this)
     * would still be honored at checkout even if it expired, was
     * deactivated, or hit its usage/per-user limit in the time between
     * - CartCalculator::summarize() itself doesn't re-check validity,
     * it trusts whatever coupon row it's handed.
     */
    private static function assertCouponStillValid(array $cartItems, ?array $coupon, int $userId): void
    {
        if ($coupon === null) {
            return;
        }

        $fresh = Coupon::findValidByCode($coupon['code']);

        if ($fresh === null) {
            throw new OrderPlacementException('Your coupon is no longer valid. Please review your cart and try again.');
        }

        $subtotal = array_reduce(
            $cartItems,
            static fn (float $carry, array $item): float => $carry + (float) $item['price'] * (int) $item['quantity'],
            0.0
        );

        if ($fresh['min_order_amount'] !== null && $subtotal < (float) $fresh['min_order_amount']) {
            throw new OrderPlacementException('Your coupon requires a higher order amount. Please review your cart and try again.');
        }

        if ($fresh['usage_limit'] !== null && (int) $fresh['used_count'] >= (int) $fresh['usage_limit']) {
            throw new OrderPlacementException('This coupon has reached its usage limit. Please review your cart and try again.');
        }

        if ($fresh['per_user_limit'] !== null && CouponUsage::countForUser((int) $fresh['id'], $userId) >= (int) $fresh['per_user_limit']) {
            throw new OrderPlacementException('You have already used this coupon the maximum number of times.');
        }
    }

    private static function decrementStock(array $item, int $orderId): void
    {
        $quantity = (int) $item['quantity'];

        if ($item['product_attribute_id'] !== null) {
            $stmt = Database::connection()->prepare(
                'UPDATE product_attributes SET stock_quantity = stock_quantity - :qty WHERE id = :id'
            );
            $stmt->execute(['qty' => $quantity, 'id' => $item['product_attribute_id']]);
        } else {
            $stmt = Database::connection()->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id'
            );
            $stmt->execute(['qty' => $quantity, 'id' => $item['product_id']]);
        }

        InventoryMovement::create([
            'product_id' => $item['product_id'],
            'type' => 'out',
            'quantity' => -$quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'note' => 'Order placement',
            'created_by' => null,
        ]);
    }
}
