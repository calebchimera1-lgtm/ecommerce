# Module 6 — Cart & Wishlist

## 1. Explanation

Full shopping cart (add/remove/update, coupon codes, shipping
selection, tax estimate, order summary, saved cart) and wishlist,
turning Module 5's disabled "Add to Cart" placeholder into a real
feature. Since the cart's coupon feature is untestable without any way
to *create* a coupon, this module also includes a small admin Coupon
CRUD - the same justification Module 5 used to pull in the static
pages the footer needed.

### Schema change: `carts.coupon_id` / `carts.shipping_method_id`

Module 1's `carts` table only had `user_id`/`session_id` - nowhere to
remember an applied coupon or chosen shipping method before checkout
exists. Two nullable columns were added, each a normal `ON DELETE SET
NULL` foreign key (matching how `orders` already references the same
two tables):

- `database/kymera_collection.sql` - updated in place (verified with a
  genuinely fresh import into an empty database, not just a diff read).
- `database/migrations/0001_add_cart_coupon_and_shipping.sql` - new;
  the `database/migrations/` folder was reserved but empty since
  Module 1. Run this only against a database created *before* this
  change - a fresh import of the main schema already includes it.

Both `carts` and its coupon/shipping-method foreign keys were added in
that order in the schema *before* `coupons`/`shipping_methods` are
defined later in the same file - a forward reference. This works
because the whole table-creation block runs under
`SET FOREIGN_KEY_CHECKS = 0` (verified directly against MariaDB before
relying on it, not assumed).

### Guest carts, "save cart," and the merge-on-login

- **Identity**: `Cart::findExisting()`/`findOrCreate()` key off
  `Auth::id()` when logged in, otherwise the raw PHP `session_id()`.
  `findExisting()` never creates a row - so browsing the site doesn't
  spawn empty cart rows; a cart is only created the moment something
  is actually added.
- **Save cart**: because a logged-in customer's cart is keyed by
  `user_id` (not a cookie), it survives across browser sessions and
  devices - verified by adding items, logging out, logging back in
  from a completely fresh cookie jar, and confirming the items are
  still there.
- **Merge on login**: `AuthController::login()` (customer) captures
  the session id *before* `Auth::login()` (which regenerates the
  session ID to prevent fixation - capturing after would grab the
  wrong, already-rotated id), then calls
  `Cart::mergeSessionCartIntoUser()`, which moves the guest cart's
  items into the user's cart (combining quantities for matching
  product+variant lines) and discards the now-empty guest cart.

### Variants and stock

The schema's `product_attributes` models one flat dimension+value row
per option (e.g. one row for "Color: Black", a separate row for
"Color: Tan"), each with its own stock and price modifier - it doesn't
support combinatorial variants (e.g. a single "Black / Large" SKU with
its own stock). Cart items follow the same model: a cart line
references at most one `product_attribute_id`. On the product page, if
any variants exist, they're offered as one flattened `<select>`
("Color: Black", "Color: Tan", ...); adding to cart validates the
selected option (if any) actually belongs to the product, and checks
requested quantity against whichever stock pool applies (the variant's
own stock if one was selected, otherwise the product's stock) -
including combined-with-existing-cart-quantity, so adding 2 more of
something you already have 3 of in your cart is checked against the
total of 5, not just the new 2.

### CartCalculator: reusable, not cart-specific

`App\Services\Cart\CartCalculator::summarize()` is a pure function
(items in, totals out - no DB writes, no session access) deliberately
kept separate from `CartController` so Module 7's checkout can reuse
it unchanged for the final order total. It computes subtotal, coupon
discount (percentage or fixed, respecting `min_order_amount` and
`max_discount_amount`), shipping cost (zeroed out above the
`free_shipping_threshold` setting seeded in Module 1), an **estimated**
tax (see below), and total.

**Tax is honestly labeled as an estimate.** The cart page can't yet
know the customer's shipping jurisdiction - address collection is
Module 7's checkout - so `TaxRate::defaultRate()` picks the store's
first active tax rate as a stand-in, and the UI both labels it
"Estimated Tax" and notes "Final tax is calculated at checkout based on
your shipping address." This is the same pattern as Module 5's
disabled Add to Cart button: an honest placeholder for a later module,
not a silently wrong calculation presented as final.

### "Proceed to Checkout" — same honest-placeholder pattern as Module 5

The cart summary's checkout button is present but disabled with a
clear label, exactly like Module 5's Add to Cart button was until this
module built the real thing. Module 7 will make it real.

## 2. Folder location / files delivered

```
app/Models/Cart.php
app/Models/CartItem.php
app/Models/Coupon.php
app/Models/CouponUsage.php
app/Models/ShippingMethod.php
app/Models/TaxRate.php
app/Models/Setting.php
app/Models/Wishlist.php
app/Services/Cart/CartCalculator.php
app/Controllers/Customer/CartController.php
app/Controllers/Customer/WishlistController.php
app/Controllers/Admin/CouponController.php
app/Views/customer/cart/index.php
app/Views/customer/wishlist/index.php
app/Views/admin/coupons/index.php
app/Views/admin/coupons/form.php
database/migrations/0001_add_cart_coupon_and_shipping.sql
```

Updated: `database/kymera_collection.sql` (carts columns),
`app/Controllers/Customer/AuthController.php` (cart merge on login),
`app/Controllers/Customer/ProductController.php` (wishlist state),
`app/Views/customer/product/show.php` (real Add to Cart + wishlist
button), `app/Views/customer/layouts/site.php` (cart icon/badge in
nav, **and the flash-alerts fix - see below**), `app/Views/admin/layouts/app.php`
(Coupons sidebar link), `routes/web.php`, `routes/admin.php`.

## 3. Routes

| Method | Path | Notes |
|---|---|---|
| GET | `/cart` | Works for guest and logged-in |
| POST | `/cart/add`, `/cart/update/{id}`, `/cart/remove/{id}` | Item ownership checked against the current visitor's cart (IDOR guard) |
| POST | `/cart/coupon`, `/cart/coupon/remove` | Apply/remove |
| POST | `/cart/shipping` | Select shipping method |
| GET | `/wishlist` | Auth required |
| POST | `/wishlist/toggle` | Auth required |
| GET/POST | `/admin/coupons`, `.../create`, `.../{id}/edit`, `.../{id}`, `.../{id}/delete` | Gated on `coupons.manage` |

## 4. SQL

See "Schema change" above. No other schema changes - uses `coupons`,
`coupon_usages`, `wishlists`, `shipping_methods`, `tax_rates`,
`settings` exactly as defined in Module 1.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance:

1. Apply the schema change: either a fresh import of
   `kymera_collection.sql`, or run
   `database/migrations/0001_add_cart_coupon_and_shipping.sql` against
   an existing database.
2. As Super Admin, create a coupon (e.g. `WELCOME10`, 10% off, $50
   minimum order) and a product with two color variants.
3. **Guest add to cart**: select a variant, add 2 - confirm the cart
   page shows the right line item, price, and subtotal.
4. **Coupon + shipping**: apply the coupon, select a shipping method,
   confirm the order summary's discount/shipping/tax/total all match
   hand-calculated values (this was checked to the cent during
   testing, including the free-shipping-threshold override).
5. **Stock validation**: try to add more of a low-stock product than
   is available (including on top of what's already in the cart) -
   rejected with the remaining-stock count in the message.
6. **Merge on login**: as a guest, add items to cart, then log in -
   confirm the guest cart's items appear under the now-logged-in
   cart, and the old guest cart row is gone.
7. **Save cart**: log out, then log back in from a *different* cookie
   jar (simulating a different browser/device) - the cart is still
   there, because it's tied to the account, not a cookie.
8. **Wishlist**: toggle add/remove from a product page, confirm the
   heart icon state and the `/wishlist` page both reflect it;
   confirm `/wishlist` redirects a guest to `/login`.
9. **Coupon edge cases**: a coupon below its `min_order_amount`, an
   unknown code, and a coupon at its `usage_limit` are all rejected
   with a specific, correct message.
10. **RBAC**: Support (no `coupons.manage`) gets 403 on
    `/admin/coupons`; Super Admin/Manager can manage coupons.

## 6. Bugs found while testing (and fixed before commit)

| Bug | File | Symptom | Fix |
|---|---|---|---|
| Storefront layout never included the shared alerts partial | `app/Views/customer/layouts/site.php` | **Every** flash message (success or error) on the entire public site - home, shop, product, cart, wishlist, contact, newsletter - was silently invisible. The underlying action always worked (DB rows were created correctly), but the user never saw confirmation or error text. This affected Module 5's pages too, not just this module's new ones - it went uncaught there because Module 5's testing checked DB effects and HTTP status codes for those flows, but only checked *rendered* flash text on the auth-layout pages (which did include the partial) | Added `require .../partials/alerts.php` inside a `<div class="container">` right after `<main>` opens in `site.php`, matching the pattern already used in the admin and auth layouts. Verified by re-running the exact coupon-rejection and newsletter-success cases that first exposed it - both now render correctly |

This is the second module in a row to surface a real defect in a
*previously shipped* module rather than (or in addition to) its own
new code - Module 5 found a Module 4 bug, and this module found a
Module 5 bug. Both were caught only because testing here happened to
exercise a flash-message path on a page type (cart) that earlier
testing hadn't specifically checked for rendered (not just triggered)
output.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `SQLSTATE[42S22]: Column not found` referencing `coupon_id` or `shipping_method_id` on `carts` | Database was created from `kymera_collection.sql` *before* this module's schema change | Run `database/migrations/0001_add_cart_coupon_and_shipping.sql` |
| A flash message doesn't appear after an action that clearly worked (item added, coupon applied) | If you're extending a customer-facing page, confirm it renders through `customer/layouts/site.php` and that layout still includes the alerts partial | Don't build a new customer layout without including `partials/alerts.php`, per the bug above |
| Coupon always rejected with "invalid or has expired" even though it exists | Coupon lookup normalizes codes to uppercase (`mb_strtoupper`) on both save and lookup - if a coupon was inserted directly via SQL with lowercase `code`, it won't match | Always create coupons through the admin UI, or uppercase the code manually if inserting directly |
| Wishlist route redirects to `/login` unexpectedly | `/wishlist` and `/wishlist/toggle` require authentication - there is no guest wishlist (the `wishlists` table has a `NOT NULL user_id`) | Expected; not a bug |

## 8. Next module

**Module 7 — Checkout, orders, payments (abstract gateway layer)**:
billing/shipping address collection, order placement (reusing
`CartCalculator` for the final total), the abstract payment gateway
architecture (Stripe/PayPal/M-Pesa/COD), order confirmation, and
invoice/email - which is what will finally turn both "Add to Cart"
and "Proceed to Checkout" into a complete purchase flow. Waiting for
confirmation to proceed.
