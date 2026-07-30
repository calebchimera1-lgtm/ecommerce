# Module 7 — Checkout, Orders, Payments

## 1. Explanation

The complete purchase flow: address collection, order review, payment,
order confirmation, printable invoice, and confirmation email - plus
the abstract payment gateway architecture the brief specifically asked
for. This is what finally makes Module 5's "Add to Cart" and Module
6's "Proceed to Checkout" add up to a real transaction.

### Why checkout requires an account (no guest checkout)

Module 1's schema declares `orders.user_id BIGINT UNSIGNED NOT NULL`.
Every order must belong to a real account - there is no schema support
for a guest order. Rather than work around an already-shipped
constraint, `/checkout` simply requires authentication
(`AuthMiddleware`), same as `/wishlist`. This is a deliberate,
schema-driven scope decision, not an oversight, and it's a common
real-world choice too (many stores require an account specifically so
order history/tracking work later).

### The abstract payment gateway architecture

- **`PaymentGatewayInterface`** — `slug()`, `label()`,
  `isConfigured()`, `charge(array $order, array $input):
  PaymentResult`. Every gateway implements exactly this; nothing else
  in checkout needs to know which gateways exist.
- **`PaymentGatewayManager`** — the only place that lists concrete
  gateways (`app/Services/Payment/Gateways/*`). Adding a fifth gateway
  later means writing one class and adding one line here.
- **Four gateways implemented**, all under
  `app/Services/Payment/Gateways/`:
  - **`CodGateway`** — Cash on Delivery. No external dependency, so it
    always succeeds; payment is recorded `pending` (money isn't
    collected until delivery, matching real COD semantics).
  - **`StripeGateway`** — real integration against Stripe's REST API
    (`POST /v1/payment_intents`) via a plain cURL call rather than the
    `stripe-php` SDK, since this codebase has no other dependency on
    it and the call shape doesn't warrant a full SDK. Card details
    never touch this server - it expects a `payment_method` ID
    produced client-side by Stripe.js, per PCI best practice.
  - **`PaypalGateway`** — real integration against PayPal's Orders API
    v2 (OAuth2 client-credentials token, then capture an order the
    customer approved client-side via the PayPal JS SDK).
  - **`MpesaGateway`** — real integration against Safaricom's Daraja
    STK Push API. **Structurally different from the other two**:
    M-Pesa is asynchronous - initiating the request only pushes a
    payment prompt to the customer's phone. A successful `charge()`
    here means "the prompt was sent" (`status: 'pending'`), not
    "paid." The callback endpoint that flips it to `'paid'` once
    Safaricom confirms is **not implemented** - it needs a public,
    signature-verified, unauthenticated endpoint, which is real scope
    of its own and moot without a publicly reachable HTTPS URL in this
    development environment. Documented here as a deliberate,
    named gap, not a silent omission.
- **Every non-COD gateway is honestly gated on `isConfigured()`.**
  Without real credentials in `.env`, they report unavailable and the
  checkout UI disables them (with a note why) rather than letting a
  customer select a payment method that can't actually charge
  anything. Server-side validation in `CheckoutController::store()`
  independently re-checks this - a crafted request bypassing the
  disabled HTML attribute still gets rejected.

**What this means for testing in this environment**: only COD could be
exercised end-to-end here (no real Stripe/PayPal/M-Pesa credentials or
outbound access to their APIs). The other three gateways are
architecturally complete and would work with real credentials, but
that's not something this sandboxed build could verify - and I'm not
claiming otherwise.

### OrderPlacementService: one atomic transaction

Order creation is genuinely all-or-nothing: order row, both addresses,
every order item, the stock decrement, an inventory movement record,
the payment attempt, and coupon usage tracking all happen inside one
`PDO` transaction. If the payment gateway declines, or stock runs out
mid-transaction, everything rolls back — no order, no stock decrement,
no cleared cart. This is simpler and safer than the alternative
(creating a payment-failed order and building a separate retry-payment
flow), and is a legitimate pattern in its own right, not just an
expedient one.

Reuses Module 6's `CartCalculator` unchanged for the final total -
the same subtotal/discount/shipping/tax math the cart page showed as
an estimate is what actually gets charged, computed fresh from the
database (never trusting client-submitted totals).

**Tax becomes final, not estimated, at this stage**: now that a real
shipping address exists, `TaxRate::forCountry()` picks a rate matching
the shipping country (falling back to the store default), fulfilling
the promise Module 6's cart page made ("final tax calculated at
checkout based on your shipping address").

**Inventory movements**: `inventory_movements` (defined in Module 1's
schema, unused until now) gets a row per order item at the moment of
purchase - the first real use of that table, not scope creep, since
recording a stock movement at the point of an order is exactly what
it's for.

### Confirmation, invoice, email

- **Confirmation page** (`/order/{orderNumber}/confirmation`) — order
  summary, both addresses, itemized totals, payment status.
- **Invoice** (`/order/{orderNumber}/invoice`) — a dedicated,
  print-optimized layout (`customer/layouts/invoice.php`, no site
  nav/footer) with a "Print / Save as PDF" button, rather than relying
  on browser-printing a page full of navigation chrome.
- **Both are ownership-checked**: `Order::findByNumberForUser()`
  requires the order to belong to the requesting user, returning a 404
  otherwise - verified directly (a second account cannot view another
  customer's order by guessing/knowing the order number).
- **Email confirmation** via the existing `Mailer` service (Module 2)
  - same dev-mode log fallback when `MAIL_HOST` is unset.

## 2. Folder location / files delivered

```
app/Services/Payment/PaymentGatewayInterface.php
app/Services/Payment/PaymentResult.php
app/Services/Payment/PaymentGatewayManager.php
app/Services/Payment/Gateways/CodGateway.php
app/Services/Payment/Gateways/StripeGateway.php
app/Services/Payment/Gateways/PaypalGateway.php
app/Services/Payment/Gateways/MpesaGateway.php
app/Services/Order/OrderPlacementService.php
app/Services/Order/OrderPlacementException.php
app/Models/Order.php
app/Models/OrderAddress.php
app/Models/OrderItem.php
app/Models/OrderStatusHistory.php
app/Models/Payment.php
app/Models/InventoryMovement.php
app/Controllers/Customer/CheckoutController.php
app/Controllers/Customer/OrderController.php
app/Views/customer/checkout/index.php
app/Views/customer/order/confirmation.php
app/Views/customer/order/invoice.php
app/Views/customer/layouts/invoice.php
app/Views/emails/order-confirmation.php
```

Updated: `app/Models/CartItem.php` (SKU in the join, `clearForCart()`),
`app/Models/TaxRate.php` (`forCountry()`), `app/Views/customer/cart/index.php`
(real "Proceed to Checkout" link, replacing Module 6's disabled
placeholder), `composer.json` (`ext-curl`), `routes/web.php`.

## 3. Routes

| Method | Path | Notes |
|---|---|---|
| GET/POST | `/checkout` | Auth required |
| GET | `/order/{orderNumber}/confirmation` | Auth required, ownership-checked |
| GET | `/order/{orderNumber}/invoice` | Auth required, ownership-checked |

## 4. SQL

No schema changes - uses `orders`, `order_addresses`, `order_items`,
`order_status_history`, `payments`, `coupon_usages`, and
`inventory_movements` exactly as defined in Module 1.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance - checked to the
cent, including database state across every affected table, not just
HTTP status codes:

1. As a logged-in customer, add an item to cart, select a shipping
   method, and visit `/checkout`. Confirm COD is selectable and
   Stripe/PayPal/M-Pesa are shown but disabled ("currently
   unavailable") since no real credentials are configured.
2. Submit the checkout form with a billing address (shipping same as
   billing) and COD. Confirm:
   - `orders` has one new row with correct subtotal/shipping/tax/total
     and a `KYM-{year}-{6-digit-id}` order number.
   - `order_addresses` has both a billing and shipping row.
   - `order_items` snapshots product name/SKU/price at time of
     purchase.
   - `payments` has a `cod`/`pending` row matching the order total.
   - `inventory_movements` logged the stock decrement, and
     `products.stock_quantity` actually decreased.
   - The cart's items were cleared and its `coupon_id`/
     `shipping_method_id` reset to `NULL`, but the cart row itself
     survives (ready for the next order).
   - `storage/logs/mail-*.log` has the order confirmation email.
3. Visit the confirmation page and the invoice page - both load and
   show correct data.
4. **Ownership check**: log in as a *different* customer and try to
   view the first customer's order by URL - `404`, both for
   confirmation and invoice.
5. **Coupon through checkout**: apply a real (active) coupon on the
   cart, then checkout - confirm the order's `discount_amount` and
   `coupon_id` are set, a `coupon_usages` row was created, and the
   coupon's `used_count` incremented.
6. **Server-side gateway re-validation**: submit `payment_method=stripe`
   directly (bypassing the disabled radio button) - rejected with
   "That payment method is not currently available," no order created.
7. **Stock race guard**: with an item already in cart, drop that
   product's stock to 0 (simulating another customer buying it first),
   then submit checkout - rejected with the specific remaining-stock
   message, no order/payment/stock-decrement side effects at all
   (transaction rolled back cleanly).
8. **Guest checkout**: an unauthenticated visit to `/checkout`
   redirects to `/login`.

## 6. Bugs found while testing (and fixed before commit)

None new. One thing initially looked like a bug during testing - a
coupon applied at checkout produced `discount_amount = 0` and
`coupon_id = NULL` on the resulting order - but investigation showed
the coupon had been created via a raw test `curl` request that (like
the checkbox gotcha documented in Module 5's testing notes) omitted
`is_active=1`, so the admin controller correctly created it inactive,
and `Coupon::findValidByCode()` correctly excluded it. Re-running with
the coupon properly active produced the correct discount, `coupon_id`,
`coupon_usages` row, and `used_count` increment. Recorded here so the
"found a bug, fixed it" pattern from earlier modules doesn't get
assumed for a module where it doesn't apply.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Every payment method except COD shows "currently unavailable" | No `STRIPE_SECRET_KEY` / `PAYPAL_CLIENT_ID`+`PAYPAL_CLIENT_SECRET` / `MPESA_CONSUMER_KEY`+... configured in `.env` | Expected in this environment; add real sandbox/live credentials to enable a gateway |
| M-Pesa "succeeds" but the order's `payment_status` never becomes `paid` | By design - M-Pesa is asynchronous. A successful `charge()` only means the STK push prompt was sent; confirmation requires the (unimplemented) Safaricom callback | Not a bug; see "Structurally different" note above. Implementing the callback endpoint is a documented follow-up |
| `curl: (7) Failed to connect` / long hangs when testing Stripe/PayPal/M-Pesa locally | Those gateways make real outbound HTTPS calls; without network access to their APIs (or without valid credentials, in which case `isConfigured()` should prevent the call from firing at all) they will fail or time out | Only test the credential-gated path if you actually have real sandbox credentials and outbound network access; otherwise rely on COD for local testing |
| `SQLSTATE[23000]` foreign key violation when placing an order | Cart references a product/attribute/coupon/shipping method that was deleted after being added to cart | Not expected in normal use; if hit, clear the cart and re-add current products |

## 8. Next module

**Module 8 — Customer dashboard, order history, tracking, reviews**:
a real `/account` dashboard (Module 2's placeholder gets replaced),
order history listing (`Order::forUser()` already exists and is ready
to use), order tracking status display, and a profile/address-book
page. Waiting for confirmation to proceed.
