<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ShippingMethod;
use App\Models\TaxRate;
use App\Services\Cart\CartCalculator;

final class CartController extends Controller
{
    public function index(Request $request): void
    {
        $cart = Cart::findExisting();
        $items = $cart !== null ? CartItem::forCart((int) $cart['id']) : [];
        $coupon = $cart !== null && $cart['coupon_id'] !== null ? Coupon::find((int) $cart['coupon_id']) : null;
        $shippingMethod = $cart !== null && $cart['shipping_method_id'] !== null
            ? ShippingMethod::find((int) $cart['shipping_method_id'])
            : null;
        $taxRate = TaxRate::defaultRate();

        $this->view('customer/cart/index', [
            'pageTitle' => 'Your Cart | Kymera Collection',
            'items' => $items,
            'coupon' => $coupon,
            'shippingMethod' => $shippingMethod,
            'shippingMethods' => ShippingMethod::activeOrdered(),
            'taxRate' => $taxRate,
            'summary' => CartCalculator::summarize($items, $coupon, $shippingMethod, $taxRate),
        ], 'customer/layouts/site');
    }

    public function add(Request $request): void
    {
        $slug = (string) $request->input('slug');
        $product = Product::findActiveBySlug($slug);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        $attributeId = null;
        $unitPrice = $product['sale_price'] ?? $product['price'];
        $availableStock = (int) $product['stock_quantity'];
        $rawAttributeId = trim((string) $request->input('product_attribute_id', ''));

        if ($rawAttributeId !== '') {
            $attribute = ProductAttribute::find((int) $rawAttributeId);

            if ($attribute === null || (int) $attribute['product_id'] !== (int) $product['id']) {
                Session::flash('errors', ['cart' => ['Please select a valid option.']]);
                $this->redirect('/product/' . $slug);
            }

            $attributeId = (int) $attribute['id'];
            $unitPrice = (string) ((float) $unitPrice + (float) $attribute['price_modifier']);
            $availableStock = (int) $attribute['stock_quantity'];
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        $cart = Cart::findOrCreate();
        $existing = CartItem::findMatching((int) $cart['id'], (int) $product['id'], $attributeId);
        $alreadyInCart = $existing !== null ? (int) $existing['quantity'] : 0;

        if ($alreadyInCart + $quantity > $availableStock) {
            Session::flash('errors', [
                'cart' => ["Only {$availableStock} available - you already have {$alreadyInCart} in your cart."],
            ]);
            $this->redirect('/product/' . $slug);
        }

        CartItem::addOrIncrement((int) $cart['id'], (int) $product['id'], $attributeId, $quantity, (string) $unitPrice);

        Session::flash('success', 'Added to your cart.');
        $this->redirect('/cart');
    }

    public function update(Request $request): void
    {
        $itemId = (int) $request->route('id');
        [$item, $ownsIt] = $this->ownedCartItem($itemId);

        if (!$ownsIt) {
            Response::abort(404, 'Cart item not found.');
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        $availableStock = self::availableStockFor($item);

        if ($quantity > $availableStock) {
            Session::flash('errors', ['cart' => ["Only {$availableStock} available."]]);
        } else {
            CartItem::update($itemId, ['quantity' => $quantity]);
            Session::flash('success', 'Cart updated.');
        }

        $this->redirect('/cart');
    }

    public function remove(Request $request): void
    {
        $itemId = (int) $request->route('id');
        [, $ownsIt] = $this->ownedCartItem($itemId);

        if ($ownsIt) {
            CartItem::delete($itemId);
            Session::flash('success', 'Item removed.');
        }

        $this->redirect('/cart');
    }

    public function applyCoupon(Request $request): void
    {
        $code = trim((string) $request->input('code', ''));
        $cart = Cart::findOrCreate();

        if ($code === '') {
            Session::flash('errors', ['coupon' => ['Please enter a coupon code.']]);
            $this->redirect('/cart');
        }

        $coupon = Coupon::findValidByCode($code);

        if ($coupon === null) {
            Session::flash('errors', ['coupon' => ['This coupon code is invalid or has expired.']]);
            $this->redirect('/cart');
        }

        $items = CartItem::forCart((int) $cart['id']);
        $subtotal = array_reduce(
            $items,
            static fn (float $carry, array $item): float => $carry + (float) $item['price'] * (int) $item['quantity'],
            0.0
        );

        if ($coupon['min_order_amount'] !== null && $subtotal < (float) $coupon['min_order_amount']) {
            Session::flash('errors', ['coupon' => ['This coupon requires a minimum order of ' . money($coupon['min_order_amount']) . '.']]);
            $this->redirect('/cart');
        }

        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            Session::flash('errors', ['coupon' => ['This coupon has reached its usage limit.']]);
            $this->redirect('/cart');
        }

        $userId = Auth::id();

        if ($userId !== null && $coupon['per_user_limit'] !== null) {
            $used = CouponUsage::countForUser((int) $coupon['id'], $userId);

            if ($used >= (int) $coupon['per_user_limit']) {
                Session::flash('errors', ['coupon' => ['You have already used this coupon the maximum number of times.']]);
                $this->redirect('/cart');
            }
        }

        Cart::applyCoupon((int) $cart['id'], (int) $coupon['id']);
        Session::flash('success', 'Coupon applied.');
        $this->redirect('/cart');
    }

    public function removeCoupon(Request $request): void
    {
        $cart = Cart::findExisting();

        if ($cart !== null) {
            Cart::applyCoupon((int) $cart['id'], null);
        }

        Session::flash('success', 'Coupon removed.');
        $this->redirect('/cart');
    }

    public function setShipping(Request $request): void
    {
        $cart = Cart::findOrCreate();
        $methodId = (int) $request->input('shipping_method_id');
        $method = ShippingMethod::find($methodId);

        if ($method === null || (int) $method['is_active'] !== 1) {
            Session::flash('errors', ['shipping' => ['Please select a valid shipping method.']]);
        } else {
            Cart::setShippingMethod((int) $cart['id'], $methodId);
            Session::flash('success', 'Shipping method updated.');
        }

        $this->redirect('/cart');
    }

    /**
     * @return array{0:?array,1:bool} [item row (or null), whether the current visitor's cart owns it]
     */
    private function ownedCartItem(int $itemId): array
    {
        $item = CartItem::find($itemId);
        $cart = Cart::findExisting();

        $owns = $item !== null && $cart !== null && (int) $item['cart_id'] === (int) $cart['id'];

        return [$item, $owns];
    }

    private static function availableStockFor(array $item): int
    {
        if ($item['product_attribute_id'] !== null) {
            $attribute = ProductAttribute::find((int) $item['product_attribute_id']);

            return $attribute !== null ? (int) $attribute['stock_quantity'] : 0;
        }

        $product = Product::find((int) $item['product_id']);

        return $product !== null ? (int) $product['stock_quantity'] : 0;
    }
}
