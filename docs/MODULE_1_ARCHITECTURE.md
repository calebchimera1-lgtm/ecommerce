# Module 1 — Architecture, Folder Structure, Database Schema, Configuration

## 1. Explanation

This module lays the foundation everything else builds on:

- A **custom MVC core** (`app/Core`) — no framework — with a front
  controller, router, base Controller/Model, PDO database singleton,
  secure session handling, CSRF protection, input validator, and a
  file logger.
- A **fully normalized MySQL 8 schema** (41 tables) covering RBAC,
  users/addresses, full product catalog with variants and images,
  cart, coupons, shipping/tax, orders with immutable snapshots,
  an abstract `payments` table ready for any gateway, inventory,
  purchasing, expenses, blog/content, and settings/notifications/audit
  logging.
- **Environment-driven configuration** (`.env` + `config/*.php`) so
  secrets never live in source control, with sane defaults for local
  XAMPP development.
- A working **skeleton request cycle**: `public/index.php` →
  `App::run()` → `Router::dispatch()` → `HomeController::index()` →
  a themed placeholder view, plus a `/health` JSON endpoint — proving
  the whole stack boots end-to-end before real features are layered on.

### Why one `users` table instead of separate `users` + `admins` tables

The brief lists "Users" and "Admins" as separate DB entities, but also
asks for full **Role-Based Access Control**. The standard, correctly
normalized way to satisfy both is a single `users` table with a
`role_id` foreign key into `roles` (Super Admin / Manager / Support /
Customer), and a `role_permissions` matrix. This avoids duplicating
identical account columns (email, password_hash, status, timestamps...)
across two tables and avoids the classic "which table does this login
belong to" bug. Staff vs. customer behavior is enforced entirely by
role/permission checks in middleware (Module 3), not by table choice.

## 2. Folder structure

```
ecommerce/
├── app/
│   ├── Controllers/
│   │   ├── Admin/            (empty - populated in later modules)
│   │   ├── Customer/
│   │   │   └── HomeController.php
│   │   └── Api/
│   ├── Core/                 <- MVC framework internals
│   │   ├── App.php           Bootstraps config, error handling, session, router
│   │   ├── Router.php        Route registration + dispatch + per-route middleware
│   │   ├── Controller.php    Base controller (view render, json, redirect, validate)
│   │   ├── Model.php         Base Active-Record-style model (PDO prepared statements)
│   │   ├── Database.php      Lazy PDO singleton
│   │   ├── Request.php       Sanitized request wrapper
│   │   ├── Response.php      JSON/redirect/abort + security headers
│   │   ├── Session.php       Secure session bootstrap + flash data
│   │   ├── Csrf.php          CSRF token generation/verification
│   │   ├── Validator.php     Rule-based input validation
│   │   ├── Logger.php        File-based logger (storage/logs)
│   │   ├── Env.php           .env loader
│   │   └── MiddlewareInterface.php
│   ├── Middleware/           (populated in Module 3 - Auth/Admin/Guest/RateLimit)
│   ├── Helpers/              (functions.php etc. added as needed)
│   ├── Services/
│   │   ├── Payment/          Abstract payment gateway architecture (Module 7)
│   │   ├── Shipping/         Shipping rate/courier services (Module 7)
│   │   └── Notification/     Email/notification services
│   ├── Models/                (empty - populated per-module, e.g. Product.php, Order.php)
│   └── Views/
│       ├── admin/            One subfolder per admin module (dashboard, products, orders...)
│       ├── customer/         home, shop, product, cart, checkout, account, auth, static
│       ├── partials/         Shared header/footer/nav/mega-menu fragments
│       ├── emails/           Transactional email templates
│       └── errors/           404.php, 500.php
├── config/
│   ├── config.php            App/mail/session/security/payment/upload settings
│   ├── database.php          PDO connection settings
│   └── .htaccess             Deny all direct web access
├── database/
│   ├── kymera_collection.sql Full schema + seed data (this module's deliverable)
│   ├── migrations/           Reserved for future incremental migrations
│   ├── seeders/              Reserved for demo/sample data seed scripts
│   └── .htaccess             Deny all direct web access
├── public/                   <- Web server DocumentRoot points here
│   ├── index.php             Front controller (only PHP file served directly)
│   ├── .htaccess             Rewrites everything to index.php
│   ├── assets/{css,js,images}
│   └── uploads/{products,categories,brands,avatars,blog}
├── routes/
│   └── web.php                Customer-facing routes (admin.php / api.php join later)
├── storage/
│   ├── logs/                  App log files (daily rotated)
│   ├── cache/
│   ├── sessions/
│   └── .htaccess              Deny all direct web access
├── docs/
│   └── MODULE_1_ARCHITECTURE.md (this file)
├── .env.example
├── .gitignore
├── .htaccess                  Root-level safety net if DocumentRoot isn't /public
├── composer.json
└── README.md
```

## 3. Files delivered in this module

| File | Purpose |
|---|---|
| `database/kymera_collection.sql` | Complete schema (41 tables) + seed data |
| `config/config.php`, `config/database.php` | Environment-driven configuration |
| `.env.example` | Template for local secrets |
| `app/Core/*.php` | MVC framework core (10 files, listed above) |
| `app/Controllers/Customer/HomeController.php` | Proof-of-life controller |
| `app/Views/customer/home/index.php` | Themed placeholder homepage |
| `app/Views/errors/404.php`, `500.php` | Error pages |
| `routes/web.php` | Route table |
| `public/index.php`, `public/.htaccess` | Front controller + rewrite rules |
| `.htaccess`, `storage/.htaccess`, `config/.htaccess`, `database/.htaccess` | Access-control safety nets |
| `composer.json` | PSR-4 autoloading + dependencies |

## 4. SQL

See [`database/kymera_collection.sql`](../database/kymera_collection.sql).
Highlights:

- **RBAC:** `roles`, `permissions`, `role_permissions`
- **Identity:** `users`, `user_addresses`, `password_resets`, `login_attempts`
- **Catalog:** `categories` (self-referencing for subcategories), `brands`,
  `suppliers`, `products` (with `specifications` JSON + FULLTEXT search
  index), `product_images`, `product_attributes` (color/size variants),
  `product_reviews`, `wishlists`
- **Cart:** `carts`, `cart_items`
- **Promotions/shipping/tax:** `coupons`, `coupon_usages`, `shipping_methods`, `tax_rates`
- **Orders:** `orders`, `order_addresses`, `order_items`, `order_status_history`,
  `payments` (gateway-agnostic), `shipments`, `product_returns`
- **Inventory/purchasing:** `inventory_movements`, `purchase_orders`,
  `purchase_order_items`, `expenses`
- **Content:** `blog_categories`, `blog_posts`, `blog_comments`, `testimonials`
- **System:** `settings`, `notifications`, `audit_logs`,
  `newsletter_subscribers`, `contact_messages`

Every FK has an explicit `ON DELETE` strategy (`CASCADE` for true
ownership, e.g. product → images; `RESTRICT` where deleting the parent
must be blocked, e.g. a role still assigned to users; `SET NULL` where
history should survive the parent's deletion, e.g. an order's product
being discontinued).

## 5. Testing instructions

1. **Import the schema:**
   ```bash
   mysql -u root -p -e "SOURCE database/kymera_collection.sql"
   # or, from the mysql CLI after `USE`:
   SOURCE /full/path/to/database/kymera_collection.sql;
   ```
   Verify: `SHOW TABLES;` should list 41 tables; `SELECT * FROM roles;`
   should return 4 rows; `SELECT * FROM users;` should return the seeded
   admin.

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   # edit DB_* to match your local MySQL credentials
   ```

4. **Serve the app** (either via XAMPP vhost pointed at `public/`, or
   quickly with PHP's built-in server for a smoke test):
   ```bash
   php -S localhost:8000 -t public
   ```

5. **Smoke test:**
   - `curl http://localhost:8000/` → should return the black/gold
     "Kymera Collection" placeholder HTML page.
   - `curl http://localhost:8000/health` → should return
     `{"status":"ok","app":"Kymera Collection"}`.
   - `curl http://localhost:8000/does-not-exist` → should return the
     themed 404 page with HTTP status 404.
   - Try to load `http://localhost:8000/../config/database.php` or
     `http://localhost:8000/../.env` directly — both must be blocked
     (404/403), proving the `.htaccess` deny rules work.

## 6. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL not running, or wrong `DB_HOST`/`DB_PORT` in `.env` | Start MySQL in XAMPP control panel; confirm `.env` matches |
| `SQLSTATE[HY000] [1045] Access denied for user` | Wrong `DB_USERNAME`/`DB_PASSWORD` | Match XAMPP's default (`root` / empty password) or your custom MySQL user |
| `Class "App\Core\App" not found` | Composer autoloader not generated | Run `composer install` (or `composer dump-autoload`) |
| Blank white page, nothing in browser | `APP_DEBUG=false` swallowing the real error | Set `APP_DEBUG=true` in `.env` temporarily and check `storage/logs/*.log` |
| `403 Forbidden` on every page | Apache `AllowOverride` is `None`, so `.htaccess` rewrite rules are ignored | In your Apache vhost/`httpd.conf`, set `AllowOverride All` for the `public/` directory, then restart Apache |
| `404` on every route except `/` | `mod_rewrite` not enabled | Enable it in `httpd.conf` (`LoadModule rewrite_module modules/mod_rewrite.so`) and restart Apache |
| `Foreign key constraint fails` when re-running the SQL file | Tables already exist from a previous partial import | Drop the database first: `DROP DATABASE IF EXISTS kymera_collection;` then re-run the script |
| `Specified key was too long` on a unique index | MySQL server not using `utf8mb4` with a modern `innodb_large_prefix`-capable version | Use MySQL 8 (as specified) — this is a non-issue on 8.x with default settings; on very old MySQL 5.6 configs, enable `innodb_large_prefix` |
| Session not persisting between requests | Browser blocking cookies, or `session.save_path` not writable | Check PHP's `session.save_path` is writable; confirm cookies aren't blocked for `localhost` |

## 7. Next module

**Module 2 — Customer authentication**: registration, login, logout,
forgot/reset password, email verification, "remember me", using the
`users`/`password_resets`/`login_attempts` tables and the
`Session`/`Csrf`/`Validator` core classes already in place. Waiting for
confirmation to proceed.
