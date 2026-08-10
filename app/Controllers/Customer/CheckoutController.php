<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Models\TaxRate;
use App\Services\Cart\CartCalculator;
use App\Services\Order\OrderPlacementException;
use App\Services\Order\OrderPlacementService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Notification\Mailer;

final class CheckoutController extends Controller
{
    public function index(Request $request): void
    {
        $cart = Cart::findExisting();
        $items = $cart !== null ? CartItem::forCart((int) $cart['id']) : [];

        if ($items === []) {
            Session::flash('errors', ['cart' => ['Your cart is empty.']]);
            $this->redirect('/cart');
        }

        [$coupon, $shippingMethod, $taxRate] = $this->resolveCartExtras($cart);

        $this->view('customer/checkout/index', [
            'pageTitle' => 'Checkout | Kymera Collection',
            'items' => $items,
            'coupon' => $coupon,
            'shippingMethod' => $shippingMethod,
            'summary' => CartCalculator::summarize($items, $coupon, $shippingMethod, $taxRate),
            'paymentGateways' => PaymentGatewayManager::available(),
        ], 'customer/layouts/site');
    }

    public function store(Request $request): void
    {
        $user = Auth::user();
        $cart = Cart::findExisting();
        $items = $cart !== null ? CartItem::forCart((int) $cart['id']) : [];

        if ($items === []) {
            Session::flash('errors', ['cart' => ['Your cart is empty.']]);
            $this->redirect('/cart');
        }

        $data = $this->validate($request->all(), [
            'billing_full_name' => 'required|max:150',
            'billing_phone' => 'required|max:30',
            'billing_address_line1' => 'required|max:255',
            'billing_city' => 'required|max:100',
            'billing_country' => 'required|max:100',
            'payment_method' => 'required',
        ]);

        $sameAsBilling = $request->input('same_as_shipping') !== null;

        $billingAddress = [
            'full_name' => $data['billing_full_name'],
            'phone' => $data['billing_phone'],
            'address_line1' => $data['billing_address_line1'],
            'address_line2' => self::nullable($request->input('billing_address_line2')),
            'city' => $data['billing_city'],
            'state' => self::nullable($request->input('billing_state')),
            'postal_code' => self::nullable($request->input('billing_postal_code')),
            'country' => $data['billing_country'],
        ];

        if ($sameAsBilling) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingData = $this->validate($request->all(), [
                'shipping_full_name' => 'required|max:150',
                'shipping_phone' => 'required|max:30',
                'shipping_address_line1' => 'required|max:255',
                'shipping_city' => 'required|max:100',
                'shipping_country' => 'required|max:100',
            ]);

            $shippingAddress = [
                'full_name' => $shippingData['shipping_full_name'],
                'phone' => $shippingData['shipping_phone'],
                'address_line1' => $shippingData['shipping_address_line1'],
                'address_line2' => self::nullable($request->input('shipping_address_line2')),
                'city' => $shippingData['shipping_city'],
                'state' => self::nullable($request->input('shipping_state')),
                'postal_code' => self::nullable($request->input('shipping_postal_code')),
                'country' => $shippingData['shipping_country'],
            ];
        }

        $paymentMethod = $data['payment_method'];

        if (!PaymentGatewayManager::isConfigured($paymentMethod)) {
            Session::flash('errors', ['payment_method' => ['That payment method is not currently available.']]);
            $this->redirect('/checkout');
        }

        [$coupon, $shippingMethod] = $this->resolveCartExtras($cart);
        $taxRate = TaxRate::forAddress($shippingAddress['country'], $shippingAddress['state']);

        $paymentInput = match ($paymentMethod) {
            'stripe' => ['payment_method' => (string) $request->input('stripe_payment_method', '')],
            'paypal' => ['paypal_order_id' => (string) $request->input('paypal_order_id', '')],
            'mpesa' => ['phone' => (string) $request->input('mpesa_phone', '')],
            default => [],
        };

        try {
            $order = OrderPlacementService::place(
                (int) $user['id'],
                (int) $cart['id'],
                $items,
                $billingAddress,
                $shippingAddress,
                $coupon,
                $shippingMethod,
                $taxRate,
                $paymentMethod,
                $paymentInput
            );
        } catch (OrderPlacementException $e) {
            Session::flash('errors', ['order' => [$e->getMessage()]]);
            $this->redirect('/checkout');
        }

        Mailer::send($user['email'], 'Order Confirmation - ' . $order['order_number'], 'order-confirmation', [
            'name' => $user['first_name'],
            'order' => $order,
            'confirmationUrl' => url('/order/' . $order['order_number'] . '/confirmation'),
        ]);

        Session::flash('success', 'Your order has been placed!');
        $this->redirect('/order/' . $order['order_number'] . '/confirmation');
    }

    /**
     * @return array{0:?array,1:?array,2:?array} [coupon, shippingMethod, taxRate]
     */
    private function resolveCartExtras(?array $cart): array
    {
        $coupon = $cart !== null && $cart['coupon_id'] !== null ? Coupon::find((int) $cart['coupon_id']) : null;
        $shippingMethod = $cart !== null && $cart['shipping_method_id'] !== null
            ? ShippingMethod::find((int) $cart['shipping_method_id'])
            : null;

        return [$coupon, $shippingMethod, TaxRate::defaultRate()];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
