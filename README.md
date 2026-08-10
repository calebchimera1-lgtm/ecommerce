# Kymera Collection

A premium, luxury e-commerce platform for fashion, shoes, watches, perfumes,
jewelry, bags, and accessories. Built with PHP 8.2+, MySQL 8, and a
hand-rolled MVC core (no framework dependency) following clean-code and
OOP principles.

**Theme:** Black / White / Gold, glassmorphism accents, luxury retail feel
inspired by Apple / Rolex / Louis Vuitton / Dior / Gucci — original design.

This project is being built module by module. See
[`docs/MODULE_1_ARCHITECTURE.md`](docs/MODULE_1_ARCHITECTURE.md) for the
foundation (architecture, folder structure, database schema, config),
[`docs/MODULE_2_AUTHENTICATION.md`](docs/MODULE_2_AUTHENTICATION.md) for
customer authentication, [`docs/MODULE_3_ADMIN_AUTH_RBAC.md`](docs/MODULE_3_ADMIN_AUTH_RBAC.md) for
admin authentication and RBAC middleware,
[`docs/MODULE_4_PRODUCT_CATALOG.md`](docs/MODULE_4_PRODUCT_CATALOG.md) for
the admin product catalog,
[`docs/MODULE_5_STOREFRONT.md`](docs/MODULE_5_STOREFRONT.md) for the
public storefront,
[`docs/MODULE_6_CART_WISHLIST.md`](docs/MODULE_6_CART_WISHLIST.md) for
cart and wishlist,
[`docs/MODULE_7_CHECKOUT_ORDERS_PAYMENTS.md`](docs/MODULE_7_CHECKOUT_ORDERS_PAYMENTS.md)
for checkout, orders, and payments, and
[`docs/MODULE_8_CUSTOMER_DASHBOARD_ORDERS_REVIEWS.md`](docs/MODULE_8_CUSTOMER_DASHBOARD_ORDERS_REVIEWS.md)
for the customer dashboard, order tracking, and reviews, and
[`docs/MODULE_9_ADMIN_DASHBOARD_ANALYTICS.md`](docs/MODULE_9_ADMIN_DASHBOARD_ANALYTICS.md)
for the admin dashboard and analytics, and
[`docs/MODULE_10_ADMIN_CATALOG_ORDER_CUSTOMER_MGMT.md`](docs/MODULE_10_ADMIN_CATALOG_ORDER_CUSTOMER_MGMT.md)
for admin customer/staff/role/review management and the audit log, and
[`docs/MODULE_11_INVENTORY_SUPPLIERS_PURCHASE_ORDERS.md`](docs/MODULE_11_INVENTORY_SUPPLIERS_PURCHASE_ORDERS.md)
for inventory, suppliers, and purchase orders, and
[`docs/MODULE_12_REPORTS_EXPORTS_EXPENSES.md`](docs/MODULE_12_REPORTS_EXPORTS_EXPENSES.md)
for reports, CSV/Excel/PDF exports, and expense management, and
[`docs/MODULE_13_BLOG_TESTIMONIALS_STATIC_SEO_SITEMAP.md`](docs/MODULE_13_BLOG_TESTIMONIALS_STATIC_SEO_SITEMAP.md)
for the blog, testimonials, static pages, SEO, and sitemap, and
[`docs/MODULE_14_SECURITY_HARDENING_FINAL_QA.md`](docs/MODULE_14_SECURITY_HARDENING_FINAL_QA.md)
for the final security audit, bug fixes, and full regression pass, and
[`docs/MODULE_15_VENDOR_MARKETPLACE_FOUNDATION.md`](docs/MODULE_15_VENDOR_MARKETPLACE_FOUNDATION.md)
for the vendor marketplace foundation (vendor accounts, auth, schema).

The 14-module single-vendor build above is being extended into a
multi-vendor marketplace (third-party sellers, commission, payouts) in
a second wave of modules - see "Marketplace extension" below.

## Tech stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6), Bootstrap 5, Font Awesome
- **Backend:** PHP 8.2+, MySQL 8, PDO (prepared statements only), Dompdf (PDF export)
- **Architecture:** MVC, OOP, PSR-12 coding style
- **Dev environment:** XAMPP + VS Code

## Quick start (XAMPP)

1. Copy the project into `htdocs/kymera-collection` (or clone this repo there).
2. Copy `.env.example` to `.env` and fill in your local DB credentials.
3. Create the database and load the schema:
   ```sql
   -- via phpMyAdmin or the mysql CLI
   SOURCE database/kymera_collection.sql;
   ```
4. Install dependencies:
   ```bash
   composer install
   ```
5. Point your Apache VirtualHost's `DocumentRoot` at the `public/` folder
   (or use the root `.htaccess` fallback if you can't change the vhost).
6. Visit `http://localhost/` (or your vhost URL). You should see the
   Kymera Collection placeholder landing page. `GET /health` returns a
   JSON status check confirming the app, router, and views are wired up.

Default seeded admin account (**change the password immediately**):

```
Email:    admin@kymeracollection.com
Password: ChangeMe!123
```

## Project status

The original 14-module single-vendor build plan is **complete**; a
marketplace extension (Modules 15-18, see below) is now in progress.

- [x] **Module 1** — Architecture, folder structure, full MySQL schema, config
- [x] **Module 2** — Customer authentication (register/login/verify/reset/remember-me)
- [x] **Module 3** — Admin authentication & RBAC middleware
- [x] **Module 4** — Product catalog (categories, brands, products, variants) — admin CRUD
- [x] **Module 5** — Storefront: home, shop, product details, search
- [x] **Module 6** — Cart & wishlist
- [x] **Module 7** — Checkout, orders, payments (abstract gateway layer)
- [x] **Module 8** — Customer dashboard, order history, tracking, reviews
- [x] **Module 9** — Admin dashboard & analytics
- [x] **Module 10** — Admin catalog/order/customer management
- [x] **Module 11** — Inventory, suppliers, purchase orders
- [x] **Module 12** — Reports & exports (PDF/Excel/CSV), expense management
- [x] **Module 13** — Blog, testimonials, static pages, SEO, sitemap
- [x] **Module 14** — Security hardening pass & final QA

See [`docs/MODULE_14_SECURITY_HARDENING_FINAL_QA.md`](docs/MODULE_14_SECURITY_HARDENING_FINAL_QA.md#8-known-limitations-and-recommendations-for-production)
for what a real production launch still needs (payment gateway
credentials, SMTP credentials, an automated test suite) before going
live. See the repo's commit history for progress on each module.

## Marketplace extension

Converting the single-vendor store above into a multi-vendor
marketplace (third-party sellers, per-category commission, vendor
payouts):

- [x] **Module 15** — Vendor marketplace foundation (schema, vendor
      accounts, vendor auth, placeholder dashboard)
- [x] **Module 16** — Vendor registration, approval & dashboard shell
      (public application form, admin approval queue, vendor
      self-service profile)
- [x] **Module 17** — Vendor product management & listing approval
      (vendor product CRUD, admin approval queue built into the
      existing product list, commission rate shown per category)
- [x] **Module 18** — Order splitting, commission & payouts (mixed-cart
      checkout splits into per-vendor sub-orders, commission calculated
      and frozen at purchase time, manual payout ledger for admin)
- [x] **Module 19** — Public vendor storefronts (`/store/{slug}` page
      per vendor, "Sold by X" links from products and order history,
      fixed a gap where a suspended vendor's products stayed visible)
- [x] **Module 20** — Vendor analytics dashboard (sales trend,
      order-status breakdown, and best-sellers, scoped to each vendor's
      own store - the vendor-side counterpart to Module 9)
- [x] **Module 21** — Vendor ratings & reviews (customers rate a
      vendor as a seller after a delivered order, gated separately
      from product reviews, moderated the same way)

The marketplace extension (Modules 15-21) is complete: vendors apply,
get approved, list and manage their own products under admin review,
get their orders split out with commission calculated and payouts
tracked, have a public storefront customers can browse and rate, and
can see how their store is performing - on top of the original
single-vendor storefront above.

## Production readiness

Closing the gaps Module 14 flagged for going live:

- [x] **Module 22** — Automated test suite (PHPUnit, dedicated
      `kymera_collection_test` database, 57 tests covering the
      riskiest logic: order splitting/commission math, public
      visibility gating including the Module 19 suspended-vendor
      regression, and validation/cart-math edge cases)
- [x] **Module 23** — CI pipeline + deployment docs (GitHub Actions
      running lint/tests/code-style on every push against a real MySQL
      service container; `DEPLOYMENT.md` covering server setup through
      a post-deploy checklist)
- [x] **Module 24** — Security review pass (exhaustive CSRF/permission
      audit across all 96 state-changing routes - 100% coverage
      confirmed, not sampled; found and fixed a real path-traversal
      gap in `ImageUploader::delete()`)
- [x] **Module 25** — Returns/refunds (RMA) workflow (per-item return
      requests on delivered orders, admin approve/reject/refund,
      restocking + vendor payout clawback that skips already-paid
      payouts, `orders.payment_status` synced once cumulative refunds
      cover the order total)
- [x] **Module 26** — Abandoned cart recovery emails (`bin/send-abandoned-cart-emails.php`,
      a cron-invoked CLI script - the first time-based, non-request-driven
      feature in this app; found and fixed a real bug where cart
      activity detection missed item-only changes since `cart_items`
      updates never touch the parent `carts` row)
- [x] **Module 27** — Multi-currency / regional tax rules (storefront
      currency switcher for display only - checkout/orders/payouts
      stay authoritative USD by design; `TaxRate` now prefers a
      state-specific rate over the flat country rate, e.g. California
      8.75% vs the old blanket 8% US rate)
- [ ] Module 28 — Vendor tiers / Top Rated Seller badges
- [ ] Module 29 — Caching layer for storefront/dashboard queries

Payment gateway and SMTP credentials remain configuration-only
(`.env` - see the Stripe/PayPal/M-Pesa/`MAIL_*` keys there) - this
batch scaffolds and documents what's needed rather than supplying real
secrets, which only the deploying party can provide.

### Running tests

```
composer test
```

Points itself at a separate `kymera_collection_test` database (never
your dev data), auto-creating and seeding it from
`database/kymera_collection.sql` on first run. See
[`docs/MODULE_22_AUTOMATED_TEST_SUITE.md`](docs/MODULE_22_AUTOMATED_TEST_SUITE.md)
for the test database strategy and what's covered, and
[`.github/workflows/ci.yml`](.github/workflows/ci.yml) for how this
runs automatically on every push/PR.

### Deploying

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the full guide - server
requirements, environment setup, database import/migration, web
server config, and exactly which payment gateway/SMTP credentials are
needed and where to get them.
