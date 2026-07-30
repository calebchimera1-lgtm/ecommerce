# Module 9 — Admin Dashboard & Analytics

## 1. Explanation

Replaces Module 3's "you're logged in" placeholder with a real admin
dashboard: every widget the brief lists, backed by actual queries
against the order/product/customer data every module since Module 4
has been generating, plus two charts.

### Sales vs. Revenue: two different numbers on purpose

The brief lists "Today's Sales," "Monthly Sales," and "Revenue" as
separate widgets. Rather than treat them as synonyms, this dashboard
gives them genuinely different meanings:

- **Sales** (`Order::sumSalesForDate()` / `sumSalesForMonth()`) — the
  gross total of every order placed, regardless of payment status.
  This is "bookings."
- **Revenue** (`Order::sumPaidRevenueAllTime()` /
  `sumPaidRevenueForMonth()`) — the total of only the orders whose
  `payment_status = 'paid'`. This is money actually collected.

Verified directly: placing a COD order (which is `unpaid` until
someone confirms delivery/collection - Module 7's design) moved the
Sales figures immediately but left Revenue at zero; manually flipping
that order's `payment_status` to `paid` then moved Revenue and
Profit, and *only* those two, leaving Sales unchanged. If these had
been implemented as the same number under two labels, that distinction
wouldn't exist and the widgets would be decorative.

**Profit** is deliberately a simplified figure:
`monthly paid revenue − monthly expenses`, not a full COGS-based
margin (which would need per-line-item cost accounting integrated with
returns/refunds - a bigger feature than this module's scope). This
simplification is stated in the UI's own breakdown line ("Revenue X −
Expenses Y"), not hidden.

### Expenses: seeded demo data, not a new CRUD

Full expense entry/management (create, edit, categorize, export) is
naturally Module 12's territory ("Reports & exports"), not this
module's. Rather than ship an Expenses widget that always reads zero,
`database/seeders/expenses.sql` provides optional demo rows - the same
pattern Module 5 used for testimonials. The `Expense` model is
read-only in this module by design (one aggregate method,
`sumForMonth()`), with a doc comment saying explicitly that write
access is deferred, not forgotten.

### The rest of the widgets

- **Best Selling Products** — `OrderItem::bestSelling()`, grouped by
  product, ordered by total quantity sold. Joins *live* product data
  (current name, image) rather than the `order_items` snapshot, so a
  since-renamed product shows correctly.
- **Low Stock Alerts** — `Product::lowStock()`, using the
  `low_stock_threshold` column that's existed since Module 1 but had
  no consumer until now (`stock_quantity <= low_stock_threshold`).
- **Latest Orders** / **Recent Customers** — straightforward, joined
  with customer name.
- **Charts** — Chart.js via CDN (no build step, consistent with every
  other JS in this project): a 14-day sales trend line
  (`Order::dailySalesTrend()`, explicitly zero-filled for days with no
  orders so the line doesn't silently skip gaps) and an order-status
  breakdown donut (`Order::statusBreakdown()`).

## 2. Folder location / files delivered

```
app/Models/Expense.php
database/seeders/expenses.sql
```

Updated: `app/Controllers/Admin/DashboardController.php` (full
rewrite - real data instead of a static welcome message),
`app/Views/admin/dashboard/index.php` (full rewrite - stat tiles,
charts, and four data tables), `app/Models/Order.php` (sales/revenue/
profit aggregates, `latest()`, `dailySalesTrend()`,
`statusBreakdown()`), `app/Models/OrderItem.php` (`bestSelling()`),
`app/Models/Product.php` (`lowStock()`), `app/Models/User.php`
(`countCustomers()`, `countNewCustomersForMonth()`,
`recentCustomers()`).

## 3. Routes

No new routes - `GET /admin/dashboard` (existing since Module 3) now
renders the real dashboard instead of the placeholder.

## 4. SQL

No schema changes. `database/seeders/expenses.sql` is new - optional
demo rows for the `expenses` table (defined in Module 1, unused until
now).

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance, checking that the
numbers are *correct*, not just non-empty:

1. Load `database/seeders/expenses.sql` for demo expense data.
2. Visit `/admin/dashboard` with no orders yet - confirm all
   sales/revenue tiles read `$0.00`, Monthly Profit correctly shows a
   *negative* number equal to `-1 × monthly expenses` (0 revenue minus
   real expenses), and the "no data yet" messages appear in place of
   empty tables.
3. Create a low-stock product (`stock_quantity` at or below
   `low_stock_threshold`) and place a COD order for it as a customer.
4. Reload the dashboard - confirm Today's Sales and Monthly Sales both
   equal the order total exactly, **Revenue stays at $0.00** (COD
   orders start `unpaid`), Order count is 1, the product now appears
   under Low Stock Alerts with its updated post-purchase stock level,
   the order appears in Latest Orders, and the sales trend chart's
   data array has the order total on today's entry and zeros on every
   other of the 14 days.
5. Manually flip that order's `payment_status` to `paid` (simulating
   what a future "mark as paid" action would do) - reload - confirm
   **only** Revenue and Profit change; Sales figures are unchanged
   (they already counted the order regardless of payment status).
6. Confirm the Support role (which has `dashboard.view` per Module 1's
   seed data) can also load the dashboard - it's a universal admin
   landing page, not gated behind a narrower permission.

## 6. Bugs found while testing (and fixed before commit)

None found in this module's own code. Testing did hit a snag caused by
leftover corrupted test data from *my own* Module 8 cleanup command (a
malformed password hash accidentally written to a test account) -
diagnosed by checking the stored hash directly, fixed by resetting it
with a real `password_hash()` call, and confirmed unrelated to any
application code. Recorded here for the same reason earlier modules
recorded test-harness-only issues: to keep "found a bug, fixed it"
claims accurate to what actually happened.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Every widget reads zero even with orders placed | Orders exist but the seeded/created ones are dated outside the current month, or `Expense`/`Order` monthly aggregates are being compared against the wrong `YEAR()`/`MONTH()` | Confirm `orders.created_at` and `expenses.expense_date` fall within the current calendar month for monthly figures; `sumSalesForDate()` uses `DATE(created_at) = CURDATE()` exactly |
| Revenue widget never moves even after "paying" an order | `payment_status` wasn't actually updated to the literal string `'paid'` (the enum in `payments`/`orders` schema), e.g. left as `'unpaid'` or `'pending'` | `UPDATE orders SET payment_status = 'paid' WHERE ...` - revenue aggregates filter on that exact value |
| Chart canvases are blank | Chart.js failed to load from the CDN (offline environment, or CSP blocking `cdn.jsdelivr.net`) - this project's CSP (`app/Core/Response.php`) already allowlists `cdn.jsdelivr.net` for scripts, so this should only happen with no internet access at all | Expected in a fully offline environment; the rest of the dashboard (tiles, tables) still renders normally since they don't depend on the chart library |

## 8. Next module

**Module 10 — Admin catalog/order/customer management**: fuller admin
tooling this module deliberately left out — a "mark payment as paid"
action, customer account management (the `users`/`customers.manage`
permission exists but has no admin UI yet), and broader order actions
beyond the minimal status/shipment forms Module 8 added. Waiting for
confirmation to proceed.
