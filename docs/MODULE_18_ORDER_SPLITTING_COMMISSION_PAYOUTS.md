# Module 18 — Order Splitting, Commission & Payouts

## 1. Explanation

The last of the four marketplace modules, and the one that makes the
first three actually pay for themselves: when a customer checks out
with a cart containing items from one or more vendors, this module
splits that single order into per-vendor sub-orders, calculates the
platform's commission on each vendor's slice at their category's
effective rate (Module 15's schema, Module 17's on-screen preview -
finally the number that's actually charged), and gives admin a ledger
to record the manual payouts Module 15 committed to.

### The split happens inside the existing checkout transaction, not after it

`OrderPlacementService::place()` already ran entirely inside one DB
transaction (Module 7) so a failure anywhere - insufficient stock, a
declined payment - rolls back the whole order, not a partially-created
one. The vendor split needed to be part of that same atomicity: a
`vendor_orders` row existing without its `order_items` correctly
linked to it (or vice versa) would be a data integrity bug, not a
recoverable state. So the split isn't a separate step that runs after
`place()` returns - it's two new private methods,
`createOrderItems()` and `splitIntoVendorOrders()`, called from inside
the same `try` block, before the payment gateway is even charged. If
anything fails later in the same transaction (the gateway declining,
for instance), the vendor_orders rows roll back with everything else -
there is no code path that leaves a paid order without its vendor
split, or a vendor split without a paid order.

### Commission is a snapshot, not a live lookup

`order_items.commission_rate` and `commission_amount` are computed
once, at the moment `createOrderItems()` runs, from whatever the
product's category's effective rate is *right then* - and then stored.
If an admin changes a category's commission rate next month, every
order placed before that change keeps the rate that was actually in
effect when the customer paid. This is the same reasoning
`order_items.price`/`product_name` already followed before this
module existed (a price change or rename shouldn't rewrite history) -
commission is just one more thing about a sale that needs to freeze at
purchase time. `vendor_orders.commission_amount`/`payout_amount` are
sums of those frozen per-item snapshots, not a live recalculation
either.

### Platform items never get a vendor_orders row

`createOrderItems()` only adds an item to `$vendorGroups` when
`vendor_id !== null`; `splitIntoVendorOrders()` only creates rows for
groups that exist. A cart made entirely of platform products
(`vendor_id NULL`, exactly how every product looked before Module 15)
produces zero `vendor_orders` rows and every `order_items.vendor_order_id`
stays `NULL` - the order behaves exactly as it did before this module,
tracked by the parent `orders.status` alone, no vendor-fulfillment UI
appears anywhere for it. Verified live: a platform-only checkout
creates the order with no `vendor_orders` rows, and the admin order
page's "Vendor Fulfillment" section (gated on `!empty($vendorOrders)`)
doesn't render at all for it.

### Each vendor's slice is a fully independent fulfillment record

A `vendor_orders` row has its own `status` (pending → processing →
shipped → delivered, or cancelled) and its own
`vendor_order_status_history` trail, updatable from three places that
all write through the same `VendorOrder::updateStatus()`: the owning
vendor's own portal (`Vendor\OrderController`, ownership-checked via
`VendorOrder::findForVendor()` exactly like Module 17's product
ownership checks), admin overriding it from the order detail page
(staff oversight for when a vendor forgets), or - never - another
vendor, who gets a 404 attempting to reach a sub-order that isn't
theirs. The parent order's own `status` is completely unaffected by
any of this; in an order split across two vendors, one vendor marking
their slice "delivered" has no effect on the other vendor's slice or
on the order's own top-level status field, which still belongs
entirely to admin (Module 10, unchanged by this module).

### Payouts are a ledger, not a payment integration

Consistent with Module 15's decision (admin pays vendors manually,
outside the app), `vendor_orders.payout_status` is a two-state flag
(`unpaid`/`paid`) an admin flips after actually sending money by
whatever means they use - the same relationship
`vendors.payout_details` already has to real bank transfers: the app
records the information, it never moves it. `Admin\PayoutController`
gives staff two views onto the same data: an overview
(`VendorOrder::balancesByVendor()`, every vendor with at least one
order, sorted by what they're currently owed) for "who do I need to
pay this week", and a per-vendor ledger for "which specific orders am
I paying for, and which have I already paid" - marking one paid
records who confirmed it, when, and an optional free-text reference
(`payout_reference`, e.g. a bank transfer ID), and is guarded against
double-marking an already-paid order.

### "Sold by X" reaches every order view for free

`OrderItem::forOrder()` gained a `LEFT JOIN` to `vendor_orders`/`vendors`
that resolves each item's selling vendor's store name (`NULL` for
platform items) - one query change that every existing caller
(customer's own order page, admin's order page, the order-confirmation
and invoice pages) picked up automatically, since they all already
call this same method. Only the customer and admin order-detail *views*
were touched to actually display the new column; confirmation/invoice
pages were left as they were, since surfacing it there wasn't asked
for and the column existing unused in their `$items` data costs
nothing.

## 2. Folder location / files delivered

```
app/Models/VendorOrder.php
app/Models/VendorOrderStatusHistory.php
app/Controllers/Vendor/OrderController.php
app/Controllers/Vendor/PayoutController.php
app/Controllers/Admin/PayoutController.php
app/Views/vendor/orders/{index,show}.php
app/Views/vendor/payouts/index.php
app/Views/admin/payouts/{index,vendor}.php
database/migrations/0006_add_vendor_orders_and_payouts.sql
```

Updated: `app/Services/Order/OrderPlacementService.php` (the split
itself), `app/Models/OrderItem.php` (`forOrder()` vendor join,
`forVendorOrder()`), `app/Models/CartItem.php` (`forCart()` now also
selects `vendor_id`/`category_id`, needed to compute commission at
checkout), `app/Controllers/Admin/OrderController.php`
(`updateVendorOrderStatus()`), `app/Controllers/Vendor/DashboardController.php`
(payout/order stats), `app/Views/vendor/{layouts/app,dashboard/index}.php`
(My Orders + Payouts nav, new stat tiles), `app/Views/admin/layouts/app.php`
(Payouts nav entry), `app/Views/admin/orders/show.php` (Sold By
column, Vendor Fulfillment section), `app/Views/admin/vendors/show.php`
(Payout Ledger link), `app/Views/customer/account/orders/show.php`
("Sold by X"), `routes/vendor.php` / `routes/admin.php` (new routes),
`database/kymera_collection.sql` (`vendor_orders`,
`vendor_order_status_history`, `order_items` columns).

## 3. Routes

```
GET  /vendor/orders                                               VendorMiddleware
GET  /vendor/orders/{id}                                          VendorMiddleware (ownership-checked)
POST /vendor/orders/{id}/status                                   VendorMiddleware (ownership-checked)
GET  /vendor/payouts                                               VendorMiddleware

POST /admin/orders/{id}/vendor-orders/{vendorOrderId}/status       orders.manage
GET  /admin/payouts                                                vendors.manage
GET  /admin/payouts/{id}                                           vendors.manage
POST /admin/payouts/{id}/{vendorOrderId}/mark-paid                 vendors.manage
```

## 4. SQL

`database/migrations/0006_add_vendor_orders_and_payouts.sql`:

- New `vendor_orders` table - one row per (order, vendor) pair:
  `status` (fulfillment), `subtotal`/`commission_amount`/`payout_amount`,
  `payout_status`, `paid_at`/`paid_by`/`payout_reference`.
- New `vendor_order_status_history` table, mirroring
  `order_status_history`'s shape exactly.
- `order_items.vendor_order_id` (nullable FK), `commission_rate`
  `DECIMAL(5,2)`, `commission_amount DECIMAL(12,2)` (both nullable -
  `NULL` for platform items).

Applied to `kymera_collection.sql` directly for fresh installs, and
via this migration for databases seeded before it.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the
actual running app - a full checkout through the live storefront, not
direct order inserts:

1. **Mixed-cart checkout**: logged in as a customer, added one
   platform product and one vendor product (Milano Leather Goods) to
   the cart, checked out via Cash on Delivery through the real
   `/checkout` form. Confirmed via direct query: the platform item's
   `order_items` row has `vendor_order_id`/`commission_rate`/
   `commission_amount` all `NULL`; the vendor item's row has
   `vendor_order_id` set, `commission_rate = 15.00` (the category had
   no override, so the platform default applied), and
   `commission_amount` = subtotal × 15% exactly; a `vendor_orders` row
   exists with `subtotal`/`commission_amount`/`payout_amount` summing
   correctly (`payout_amount = subtotal - commission_amount`) and
   `status`/`payout_status` both starting at their initial values.
2. **Platform-only checkout** (edge case): a second checkout with only
   a platform item produced zero `vendor_orders` rows and a `NULL`
   `vendor_order_id` on its one item - confirming the split is
   additive and doesn't create empty/spurious sub-orders. Confirmed
   the admin order page's Vendor Fulfillment section doesn't render
   for this order at all.
3. **Vendor fulfillment**: as the vendor, `/vendor/orders` listed the
   new sub-order with the correct subtotal/commission/payout figures;
   opening it showed only that vendor's own item (not the platform
   item from the same parent order) and the real shipping address.
   Updated its status to `processing` with a note - confirmed the
   `vendor_orders.status` change and a `vendor_order_status_history`
   row with the vendor's own user id as `changed_by`.
4. **Admin oversight**: the admin order detail page showed both
   "Platform" and "Milano Leather Goods" in a new Sold By column, and
   a Vendor Fulfillment section with the same sub-order. Used admin's
   own status-update form on that sub-order (`shipped`) - confirmed it
   updated the same `vendor_orders` row and history trail, with the
   admin's user id recorded as `changed_by` this time.
5. **Cross-vendor isolation**: a second vendor's session got a 404
   requesting the first vendor's sub-order id directly - `VendorOrder::findForVendor()`
   scopes every vendor-portal lookup exactly like Module 17's product
   ownership checks.
6. **"Sold by" on the customer side**: the customer's own order-detail
   page showed "Sold by Milano Leather Goods" under the vendor item
   and nothing under the platform item.
7. **Payout ledger**: `/admin/payouts` listed the vendor with their
   correct unpaid balance; drilling into their ledger
   (`/admin/payouts/{id}`) showed the specific order and a Mark Paid
   action. Marked it paid with a reference note - confirmed
   `payout_status → paid`, `paid_at`/`paid_by`/`payout_reference` all
   set correctly, an `audit_logs` row (`vendor_order.paid`), the
   overview's unpaid balance dropping to zero and paid-to-date
   increasing by the same amount, and the vendor's own `/vendor/payouts`
   page reflecting the identical numbers. Attempting to mark the same
   payout paid a second time was refused (guarded on `payout_status !== 'paid'`)
   without altering the existing payout record.
8. Confirmed the vendor dashboard's new stat tiles (Orders To Date,
   Unpaid Balance) reflect real numbers before and after the payout
   was marked paid.
9. Full regression sweep: stock correctly decremented for both items
   regardless of vendor ownership (unchanged `decrementStock()` logic);
   home, shop, admin dashboard, admin orders list, and customer order
   history all still 200; unauthenticated requests to
   `/vendor/orders` and `/admin/payouts` redirect rather than exposing
   anything; every new/changed file passes `php -l` with zero syntax
   errors.

## 6. Bugs found while testing (and fixed before commit)

None. Testing again hit the same PHP-built-in-dev-server quirk
documented in every module since Module 10 (first POST after a fresh
cookie jar occasionally 419s, always succeeds on immediate retry) -
confirmed once more as a dev-server artifact, not re-litigated per
instance.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| An order's vendor item shows `commission_rate`/`commission_amount` as `NULL` | The product's `vendor_id` was `NULL` at the moment of purchase (platform-owned) | Expected - only vendor-owned items get a commission snapshot; check `SELECT vendor_id FROM products WHERE id = ...` if this is unexpected |
| A vendor sees no orders on `/vendor/orders` despite products selling | Their products may all be platform-owned, or no order containing their product has been placed since Module 18 shipped (orders placed before this migration have no retroactive split) | Confirm via `SELECT * FROM vendor_orders WHERE vendor_id = ...`; this module does not backfill splits for pre-existing orders |
| 404 opening a vendor sub-order that clearly exists | The `vendor_orders.id` in the URL belongs to a different vendor | Expected and intentional - `VendorOrder::findForVendor()` scopes every lookup to the calling vendor's own id, the same ownership pattern Module 17 established for products |
| "This payout is already marked as paid" | Attempted to mark-paid a `vendor_orders` row whose `payout_status` is already `paid` | Expected - reload the vendor's payout ledger to see the current state; there is no un-mark-paid action by design (correct a mistake directly in the database if one is ever made) |
| Vendor Fulfillment section missing from an order's admin page | That order has no vendor-owned items - `vendor_orders` for it is empty | Expected; the section only renders when `$vendorOrders` is non-empty |

## 8. Next module

This completes the four-module marketplace extension (Modules 15-18).
Kymera Collection is now a real multi-vendor marketplace: vendors
apply and get approved (16), list and manage their own products under
admin review (17), and get their orders split out with commission
calculated and payouts tracked (18), on top of the foundation schema
and account type Module 15 laid down. No further module is planned
unless new requirements come up.
