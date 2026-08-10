# Module 20 — Vendor Analytics Dashboard

## 1. Explanation

Admin has had a rich, chart-driven dashboard since Module 9 - sales
trend, order status breakdown, best sellers - built entirely from
platform-wide `orders`/`order_items` data. A vendor's own landing page
(Modules 15-18) stayed a simple stat-tile summary by comparison. This
module gives vendors the same depth of insight Module 9 gave admin,
scoped to their own store: a new **Analytics** page with a sales trend
chart, an order-status breakdown, and a best-sellers table, all
computed from `vendor_orders`/`order_items` filtered to one
`vendor_id`.

### A new page, not a bigger Dashboard

`Vendor\AnalyticsController` is deliberately separate from
`Vendor\DashboardController` rather than folding these charts into the
existing landing page. The vendor Dashboard has stayed a lightweight
"here's what needs attention right now" view since Module 15 (account
status, live/pending product counts, unpaid balance) - the same role
Module 9's admin Dashboard plays for staff. Analytics is the "how is my
store actually doing over time" view, the same role the admin side's
later Reports section (`Admin\ReportController`) plays relative to its
own Dashboard. Keeping them as separate nav destinations mirrors that
existing split instead of inventing a new one, and means a vendor who
only wants the quick status check on login isn't served an
image-heavy, chart.js-loading page by default.

### Every query is vendor-scoped, mirroring the admin originals exactly

Each new `VendorOrder` method is a scoped copy of an existing `Order`
aggregate, same SQL shape, same zero-fill behavior, with one added
`WHERE vendor_id = :vendor_id`:
`sumSubtotalForDate`/`countForDate`/`sumSubtotalForMonth` mirror
`Order::sumSalesForDate`/`countForDate`/`sumSalesForMonth`;
`dailySalesTrend()` mirrors `Order::dailySalesTrend()` (same 14-day
zero-filled loop, just summing `vendor_orders.subtotal` instead of
`orders.total`); `statusBreakdown()` mirrors `Order::statusBreakdown()`
(vendor fulfillment statuses instead of the platform's order
statuses). `OrderItem::bestSellingForVendor()` mirrors
`OrderItem::bestSelling()`, joined through `vendor_orders` rather than
`products.vendor_id` directly, so it only ever counts quantity/revenue
actually attributed to *this vendor's* sub-orders - consistent with
every other vendor-scoped query added since Module 17 (ownership
checks go through the relationship a vendor actually owns, not a
column that merely happens to be filterable).

### "Sales" vs "payout" - the vendor sees both, deliberately

A vendor's stat tiles distinguish **Monthly Sales** (their gross
subtotal - what customers paid for their items) from **Monthly Payout
Earned** (subtotal minus commission - what they'll actually receive).
Showing only one would be misleading in different directions: gross
sales alone overstates what a vendor takes home; payout alone hides
how much of their revenue the platform's commission consumes. Both
numbers were already being calculated and stored per-order since
Module 18 (`vendor_orders.subtotal`/`commission_amount`/`payout_amount`)
- this module is the first to aggregate them into monthly/all-time/
trend figures a vendor can actually act on.

## 2. Folder location / files delivered

```
app/Controllers/Vendor/AnalyticsController.php
app/Views/vendor/analytics/index.php
```

Updated: `app/Models/VendorOrder.php` (`sumSubtotalForDate`,
`countForDate`, `sumSubtotalForMonth`, `sumPayoutForMonth`,
`sumSubtotalAllTime`, `dailySalesTrend`, `statusBreakdown`),
`app/Models/OrderItem.php` (`bestSellingForVendor`),
`app/Views/vendor/layouts/app.php` (Analytics nav entry),
`routes/vendor.php` (`/vendor/analytics`).

## 3. Routes

```
GET /vendor/analytics    VendorMiddleware
```

## 4. SQL

None. Entirely application code over Module 18's existing
`vendor_orders`/`order_items` schema.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the
actual running app:

1. Logged in as a vendor with one prior order (Milano Leather Goods,
   from Module 18's checkout test). `/vendor/analytics` → 200.
   Cross-checked every stat tile against a direct query on the same
   data: All-Time Sales ($498.00) and Total Orders (1) matched
   `SELECT SUM(subtotal), COUNT(*) FROM vendor_orders WHERE vendor_id = ...`
   exactly.
2. Best Selling Products table showed the correct product, units sold
   (2), and revenue ($498.00), matching `order_items` for that
   vendor's sub-order.
3. Orders by Status chart data (`statusData`/`statusLabels` embedded
   in the page) showed `["Shipped"]`/`[1]`, matching the vendor order's
   real status (set to `shipped` during Module 18's admin-override
   test) - confirming the aggregate reflects live status, not a
   snapshot from order-placement time.
4. Sales Trend chart data showed the order's $498 subtotal on the
   correct calendar day within the 14-day window, every other day
   zero-filled.
5. **Empty state**: logged in as a second, approved vendor with zero
   orders placed against them. `/vendor/analytics` → 200 with no PHP
   errors, "No orders yet"/"No sales yet" messaging in place of the
   best-sellers table and status chart, all stat tiles correctly
   showing zero/`$0.00` rather than failing on empty aggregates. The
   status-breakdown canvas and its Chart.js instantiation are both
   omitted entirely when there's no data (verified the `<canvas
   id="statusChart">` markup and its script block are both absent from
   the response), rather than rendering a broken empty chart.
6. Full regression sweep: home, shop, and every other vendor-portal
   page (dashboard, products, orders, payouts) still 200 for an
   authenticated vendor; an unauthenticated request to
   `/vendor/analytics` redirects to vendor login rather than exposing
   anything; every new/changed file passes `php -l` with zero syntax
   errors.

## 6. Bugs found while testing (and fixed before commit)

None. Testing again hit the same PHP-built-in-dev-server quirk
documented in every module since Module 10 (first POST after a fresh
cookie jar occasionally 419s, always succeeds on immediate retry), not
re-litigated per instance.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Status breakdown chart missing even though the vendor has orders | Expected only when `VendorOrder::statusBreakdown()` returns an empty array - if orders exist, this shouldn't happen | `SELECT status, COUNT(*) FROM vendor_orders WHERE vendor_id = ... GROUP BY status` should return at least one row if `countForVendor()` is non-zero; if it's empty despite orders existing, check that `vendor_orders.vendor_id` actually matches |
| Monthly Sales and Monthly Payout Earned don't add up the way expected (payout isn't simply "sales minus a flat %") | Each vendor_orders row's commission was calculated per-category at the time each item was ordered (Module 18) - a vendor selling across multiple categories with different commission rates will see a blended, not flat, effective rate | Compare against `order_items.commission_rate` for the specific items in that month; the monthly figures are correct sums, just not single-rate arithmetic |
| Best Selling Products table doesn't include a product the vendor knows they've sold | The product was later soft-deleted (`bestSellingForVendor()` excludes `p.deleted_at IS NOT NULL` rows, matching admin's `bestSelling()` behavior) | Expected - deleted products are excluded from this table the same way they're excluded from the admin equivalent; the sale still exists in `order_items`, just not surfaced here |

## 8. Next module

None currently planned. Vendor ratings & reviews (separate from
product reviews, shown on a vendor's storefront - Module 19) remains a
discussed-but-unscheduled candidate.
