# Module 15 — Vendor Marketplace Foundation

## 1. Explanation

The first of four modules converting Kymera Collection from a
single-vendor store into a marketplace where third-party vendors can
list products under a commission you set. This module ships the
foundation only - schema, vendor accounts, vendor authentication, and
a placeholder dashboard - the same shape Module 3 shipped admin
auth/RBAC with a placeholder dashboard before Module 9 built the real
one nine modules later. Self-service vendor registration and the
admin approval queue are Module 16; vendor product management and
listing approval are Module 17; order splitting, commission, and
payouts are Module 18.

Four decisions were made before writing any code, since they shape
the schema every later module builds on:

- **Commission**: per-category, not a single global rate or
  per-vendor negotiated rate - `categories.commission_rate` (nullable,
  falls back to a platform-wide `default_commission_rate` setting).
- **Fulfillment**: each vendor ships their own items - this module
  doesn't touch orders yet, but it's why Module 18 will split an order
  into per-vendor sub-orders rather than centralizing fulfillment.
- **Listing approval**: vendor-submitted products need admin approval
  before going live - hence `products.approval_status`, added now even
  though nothing uses it until Module 17.
- **Payouts**: manual - you record payouts yourself outside the app;
  `vendors.payout_details` is free-form text (bank info), not a
  Stripe Connect integration. No new payment gateway work needed.

### Vendors are a new account type, not a limited admin role

A vendor is architecturally closer to a customer than to a limited
admin: they log into their own portal, see only their own data, and
have no business touching the staff permission matrix at all. So
rather than model vendors as "an admin role with fewer permissions"
(which would mean squeezing them into `PermissionMiddleware`/`Auth::can()`,
a system built for gating sections of *one shared admin panel* between
staff members), this module gives them their own realm:

- `roles.slug = 'vendor'` (role id 5) - reuses the existing
  `users`/`roles` tables and `Auth` class rather than building a
  parallel login system from scratch.
- `vendors` table (one row per vendor account, `user_id` unique) holds
  what's specific to being a seller: store name/slug, approval status,
  payout details. The login identity (email/password) stays on `users`
  like every other account type.
- `VendorMiddleware`/`GuestVendorMiddleware` gate `/vendor/*` the way
  `AdminMiddleware`/`GuestAdminMiddleware` gate `/admin/*`, but check
  "is this a `vendor`-role user whose `vendors.status = 'approved'`"
  instead of a permission slug - there's no permission matrix to check
  because a vendor dashboard has exactly one tenant: that vendor.
- `routes/vendor.php` is a third route file alongside `routes/web.php`
  and `routes/admin.php`, merged into the same `Router` by `App::run()`.

Verified live that the status gate is re-checked on every request, not
just at login: logged a vendor in while `approved`, confirmed dashboard
access, flipped their `vendors.status` to `suspended` directly in the
database (simulating an admin suspending them), and confirmed their
*already-authenticated session* immediately lost dashboard access
(403) without needing to log out - restoring `approved` immediately
restored access. A stale "you were approved when you logged in" session
would have been a real gap in a marketplace where trust status can
change at any time.

### Why every existing product is unaffected

`products.vendor_id` is nullable and `products.approval_status`
defaults to `'approved'` - so every product created in Modules 1-14
(all of them platform-owned, `vendor_id = NULL`) keeps exactly its
current behavior: visible, no approval step, unaffected by anything
this module adds. NULL `vendor_id` means "sold by the platform
directly," not "vendor unassigned." Verified directly: the one product
already in the test database still shows `vendor_id = NULL`,
`approval_status = 'approved'` after the migration ran, unchanged.

### Commission trust tier

`vendors.manage` (the admin permission for the approval queue Module
16 builds) was added to Super Admin's grant only - explicitly excluded
from Manager's "everything except users/roles/settings/audit_logs"
blanket grant, the same way those four are excluded. Deciding who gets
to sell on your platform is a trust-tier decision closer to hiring
staff than to day-to-day catalog/order operations, which is what
Manager's broad grant is for.

## 2. Folder location / files delivered

```
app/Models/Vendor.php
app/Middleware/VendorMiddleware.php
app/Middleware/GuestVendorMiddleware.php
app/Controllers/Vendor/AuthController.php
app/Controllers/Vendor/DashboardController.php
app/Views/vendor/layouts/{login,app}.php
app/Views/vendor/auth/login.php
app/Views/vendor/dashboard/index.php
routes/vendor.php
database/migrations/0004_add_vendor_marketplace_foundation.sql
```

Updated: `app/Core/App.php` (registers `routes/vendor.php`),
`database/kymera_collection.sql` (`vendors` table; `categories.commission_rate`;
`products.vendor_id`/`approval_status`; `vendor` role; `vendors.manage`
permission; `default_commission_rate` setting).

## 3. Routes

```
GET  /vendor                redirects to /vendor/dashboard    VendorMiddleware
GET  /vendor/login                                            GuestVendorMiddleware
POST /vendor/login                                             GuestVendorMiddleware
POST /vendor/logout                                            VendorMiddleware
GET  /vendor/dashboard      placeholder                        VendorMiddleware
```

## 4. SQL

`database/migrations/0004_add_vendor_marketplace_foundation.sql`:

- New `vendors` table (business profile + approval workflow)
- `categories.commission_rate DECIMAL(5,2) NULL`
- `products.vendor_id BIGINT UNSIGNED NULL` (`ON DELETE SET NULL`) +
  `products.approval_status ENUM('approved','pending','rejected') DEFAULT 'approved'`
- New role: `Vendor` (id 5, slug `vendor`)
- New permission: `vendors.manage` (module `vendors`), granted to
  Super Admin only
- New setting: `default_commission_rate` = `15.00`

Applied to the schema file directly for fresh installs, and via this
migration for databases created before this module.

## 5. Testing instructions

No self-service registration exists yet (Module 16), so a test vendor
account was created directly via SQL - documented here as test-harness
setup, the same way earlier modules reset the seeded admin password
via SQL for testing rather than treating it as an application feature:

```sql
INSERT INTO users (uuid, role_id, first_name, last_name, email, password_hash, status, email_verified_at)
VALUES (UUID(), 5, 'Amara', 'Okafor', 'amara@...', <argon2id hash>, 'active', NOW());
INSERT INTO vendors (user_id, store_name, slug, status, approved_by, approved_at)
VALUES (<user_id>, 'Luxe Accessories Co', 'luxe-accessories-co', 'approved', 1, NOW());
```

Verified end-to-end against a real MariaDB instance through the actual
running app (not just direct queries):

1. Applied the migration; confirmed `vendors` table structure, the new
   `products`/`categories` columns, the `Vendor` role, `vendors.manage`
   permission (granted to Super Admin only, confirmed absent from
   Manager), and the `default_commission_rate` setting all exist
   exactly as designed.
2. Confirmed the pre-existing test product's `vendor_id`/`approval_status`
   are `NULL`/`approved` after the migration - unaffected.
3. Logged in as the test vendor through the real `/vendor/login` form
   - confirmed the dashboard loads and shows the vendor's actual store
   name from the database.
4. **Cross-realm boundary checks**, each done by actually logging in
   as that account type and hitting the other realm's URL: a logged-in
   customer hitting `/vendor/dashboard` → 403; a logged-in admin
   hitting `/vendor/dashboard` → 403; a logged-in vendor hitting
   `/admin/dashboard` → 403; an unauthenticated visitor hitting either
   → redirected to the right login page, not the wrong one.
5. **Approval-status gating**: set the test vendor's status to
   `pending`, `rejected` (with a reason), and `suspended` in turn and
   confirmed login was refused each time with the correct,
   status-specific message; confirmed a `rejected` vendor's message
   includes the actual `rejection_reason` text from the database.
6. **Live status re-check**: confirmed (as detailed above) that
   suspending an already-logged-in vendor immediately revokes
   dashboard access without requiring logout, and restoring `approved`
   immediately restores it - no stale per-session caching of approval
   status.
7. Confirmed rate limiting applies to vendor login exactly like
   customer/admin login (5 failed attempts, keyed `vendor:{email}` so
   it can't cross-throttle a customer/admin login attempt against the
   same email address).
8. Full regression sweep of core storefront and admin routes to
   confirm nothing broke: home, shop, admin dashboard all still 200
   for the right sessions.

## 6. Bugs found while testing (and fixed before commit)

None in this module's own code. Testing repeatedly hit the same
PHP-built-in-dev-server quirk documented in earlier modules (Module
10 onward): the first POST immediately following a fresh cookie jar +
GET occasionally returns 419 even with a correct, freshly-scraped CSRF
token, and always succeeds on an immediate retry with the same token.
Confirmed via `curl -v` that the token, cookie, and session state were
all correct on the failing attempt - this is specific to the
single-threaded dev server used for local testing, not a CSRF
verification bug, consistent with every prior instance of this same
pattern in this project.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| "No vendor profile is linked to this account" on vendor login | A `users` row has `role_id` pointing at the Vendor role but has no matching `vendors` row | Every vendor-role user must have exactly one `vendors` row (`vendors.user_id` is `UNIQUE`) - Module 16's registration flow will create both atomically |
| "This login is for vendor accounts only" | Tried to log a customer- or staff-role account in through `/vendor/login` | Use `/login` (customer) or `/admin/login` (staff) instead - `/vendor/login` explicitly checks `roles.slug = 'vendor'` |
| A vendor stays logged into the dashboard after being suspended | Shouldn't happen - `VendorMiddleware` re-checks `vendors.status` on every request, not just at login | If it does, confirm the suspending update actually committed (`SELECT status FROM vendors WHERE id = ...`) and that no stale `Vendor::findByUserId()` result is being cached somewhere outside this middleware |
| Existing (pre-Module-15) products disappear from the storefront or need approval | Should not happen - `approval_status` defaults to `'approved'` and `vendor_id` defaults to `NULL` for every row that existed before this migration | If products are missing, check `SELECT vendor_id, approval_status FROM products` - platform products must show `NULL`/`approved`; anything else means the migration ran against the wrong table state |

## 8. Next module

**Module 16 — Vendor registration, approval & dashboard shell**: a
public "Become a Vendor" application form (creating both the `users`
and `vendors` rows this module's test data faked via SQL), an admin
approval queue (`Admin\VendorController`, gated by `vendors.manage`)
to approve/reject/suspend applicants, and richer vendor dashboard
navigation as the real destination for what's currently a placeholder
page. Waiting for confirmation to proceed.
