# Module 3 — Admin Authentication & RBAC Middleware

## 1. Explanation

A separate staff login (`/admin/login`) and a role/permission-driven
access layer for the admin panel, built entirely on the `roles`,
`permissions`, and `role_permissions` tables and seed data from
Module 1. It reuses the same session mechanism as customer auth
(`App\Core\Auth`) — one login slot per browser session — but adds a
permission layer on top so "logged in" and "allowed into this admin
page" are two separate, independently testable checks.

### RBAC model

- **`AdminMiddleware`** — the base gate: authenticated *and* the
  user's role has the `dashboard.view` permission. This is "is this
  person staff at all," independent of any specific feature.
- **`PermissionMiddleware`** — a *parameterized* middleware for
  feature-specific gates, e.g. `[[PermissionMiddleware::class,
  'settings.manage']]`. Per Module 1's seed data, only Super Admin has
  `settings.manage`; Manager and Support do not, so they get a 403 on
  `/admin/settings` even though they can reach `/admin/dashboard`.
- **`GuestAdminMiddleware`** — mirror of the customer `GuestMiddleware`:
  bounces an already-logged-in staff member away from `/admin/login`
  to `/admin/dashboard`.
- **`Auth::can(string $permission)`** — session-based check for the
  *currently logged in* user; used by middleware and by views (the
  admin sidebar hides links the current role can't reach).
- **`Auth::roleCan(int $roleId, string $permission)`** — the same
  check but for an arbitrary role ID, independent of any session. Used
  during login itself (before a session exists) to reject a `customer`
  role attempting `/admin/login`, and by anything else that needs to
  ask "would role X be allowed to do Y" without that role being logged
  in right now.

Both permission checks share one query-and-cache implementation, so
there's a single source of truth for "does this role have this
permission" whether or not a session is involved.

### Router change: parameterized middleware

Module 1's router only supported middleware as a bare class name
(`AuthMiddleware::class`, instantiated with no arguments). Permission
gating needs to pass *which* permission, so `Router::resolveMiddleware()`
now also accepts `[ClassName::class, ...constructorArgs]`:

```php
$router->get('/admin/settings', [SettingsController::class, 'index'],
    [[PermissionMiddleware::class, 'settings.manage']]);
```

Existing bare-class-name middleware entries throughout Modules 1–2
still work unchanged — this is purely additive.

### Why admin login is a separate identifier for rate limiting

`AuthController::login()` (admin) throttles on `'admin:' . $email`
rather than the plain email `RateLimiter` key that customer login
uses. A customer and a staff member could plausibly share an email in
some organizations, and repeated failed customer-login attempts
shouldn't lock someone out of the admin panel, or vice versa. A
`customer`-role account attempting `/admin/login` at all still counts
as a failed *admin* attempt (rejected by `roleCan()`), so brute-forcing
the admin panel via a known customer email is still throttled.

## 2. Folder location / files delivered

```
app/Controllers/Admin/AuthController.php
app/Controllers/Admin/DashboardController.php
app/Controllers/Admin/SettingsController.php
app/Middleware/AdminMiddleware.php
app/Middleware/GuestAdminMiddleware.php
app/Middleware/PermissionMiddleware.php
app/Views/admin/layouts/login.php
app/Views/admin/layouts/app.php               (sidebar + topbar shell)
app/Views/admin/auth/login.php
app/Views/admin/dashboard/index.php            (placeholder, full module later)
app/Views/admin/settings/index.php             (read-only, editing form later)
routes/admin.php
```

Updated: `app/Core/Auth.php` (added `can()`/`roleCan()`),
`app/Core/Router.php` (parameterized middleware support),
`app/Core/App.php` (merges `routes/admin.php` alongside `routes/web.php`).

## 3. Routes

| Method | Path | Middleware | Controller action |
|---|---|---|---|
| GET | `/admin` | Admin | redirects to `/admin/dashboard` |
| GET | `/admin/login` | GuestAdmin | `Admin\AuthController::showLogin` |
| POST | `/admin/login` | GuestAdmin, CSRF | `Admin\AuthController::login` |
| POST | `/admin/logout` | Admin, CSRF | `Admin\AuthController::logout` |
| GET | `/admin/dashboard` | Admin | `Admin\DashboardController::index` |
| GET | `/admin/settings` | Permission(`settings.manage`) | `Admin\SettingsController::index` |

## 4. SQL

No schema changes — uses `roles`, `permissions`, `role_permissions`,
`users`, `login_attempts`, and `settings` exactly as seeded in Module 1.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance with all three
seeded staff roles plus a customer account, not just linted:

1. Seed a Manager and Support test account (Super Admin already exists
   from Module 1's seed data):
   ```sql
   INSERT INTO users (uuid, role_id, first_name, last_name, email, password_hash, status, email_verified_at)
   VALUES
     (UUID(), 2, 'Mia', 'Manager', 'manager@kymeracollection.com', '<argon2id hash of a test password>', 'active', NOW()),
     (UUID(), 3, 'Sam', 'Support', 'support@kymeracollection.com', '<argon2id hash of a test password>', 'active', NOW());
   ```
   (Generate a real hash with `php -r 'echo password_hash("YourTestPassword", PASSWORD_ARGON2ID);'` — don't reuse a placeholder string.)

2. **Unauthenticated access**: `GET /admin/dashboard` → redirects to
   `/admin/login`.
3. **Customer rejected**: log in to `/admin/login` with a `customer`
   role account's credentials → redirected back with "You do not have
   access to the admin panel." (No session is established.)
4. **Super Admin**: log in → `/admin/dashboard` (200) and
   `/admin/settings` (200, lists the seeded settings rows).
5. **Manager / Support**: log in → `/admin/dashboard` (200) but
   `/admin/settings` → **403**. The sidebar on their dashboard also
   does not render a Settings link (it's gated by the same
   `Auth::can('settings.manage')` check the route uses).
6. **GuestAdminMiddleware**: while logged in as any staff role,
   `GET /admin/login` → redirects to `/admin/dashboard`.
7. **Logout**: `POST /admin/logout` → `/admin/dashboard` now redirects
   to `/admin/login` again.
8. **Rate limiting**: 5 wrong-password attempts against
   `admin@kymeracollection.com`, then a 6th → "Too many failed login
   attempts" without even checking the password. Confirm in the DB
   that these rows are keyed `admin:admin@kymeracollection.com`,
   separate from any customer-login attempts against the same email.

## 6. Bugs found while testing (and fixed before commit)

| Bug | File | Symptom | Fix |
|---|---|---|---|
| PHP does not support a spread operator inside list-assignment destructuring | `app/Core/Router.php` | `[$class, ...$args] = $entry;` is a **fatal parse error** ("Spread operator is not supported in assignments") — caught immediately by `php -l`, before any runtime testing | Replaced with `$class = $entry[0]; $args = array_slice($entry, 1);` |

No other new defects surfaced during live testing this time — Modules
1–2's bug fixes (Validator numeric/length rules, Session restart after
destroy, alerts-partial path) held up under the additional admin-side
exercising.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Admin login always says "You do not have access to the admin panel" for a real staff account | `role_permissions` seed data missing/altered, or the account's `role_id` doesn't match a role that has `dashboard.view` | `SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = <the user's role_id>;` and confirm `dashboard.view` is present |
| A route protected by `[[PermissionMiddleware::class, 'x.manage']]` 500s instead of 403ing | Typo'd permission slug that doesn't exist in the `permissions` table — `Auth::can()` simply returns false for an unknown slug (correctly denies access), so a 500 here points to something else, usually a missing `use` import for `PermissionMiddleware` in the route file | Double-check the slug against `SELECT slug FROM permissions;` and the route file's `use` statements |
| Sidebar shows a link but clicking it 403s | The nav item's `permission` key in `app/Views/admin/layouts/app.php`'s `$navItems` doesn't match the permission the route itself is gated on | Keep the two in sync — the sidebar's gating is presentation-only and independent of the route's actual enforcement |

## 8. Next module

**Module 4 — Product catalog**: categories, brands, products (with
images, variants/attributes, specifications), all gated behind the
appropriate `*.manage` permissions using the `PermissionMiddleware`
infrastructure built here. Waiting for confirmation to proceed.
