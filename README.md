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
for the customer dashboard, order tracking, and reviews.

## Tech stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6), Bootstrap 5, Font Awesome
- **Backend:** PHP 8.2+, MySQL 8, PDO (prepared statements only)
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

Being delivered module by module per the build plan. Completed so far:

- [x] **Module 1** — Architecture, folder structure, full MySQL schema, config
- [x] **Module 2** — Customer authentication (register/login/verify/reset/remember-me)
- [x] **Module 3** — Admin authentication & RBAC middleware
- [x] **Module 4** — Product catalog (categories, brands, products, variants) — admin CRUD
- [x] **Module 5** — Storefront: home, shop, product details, search
- [x] **Module 6** — Cart & wishlist
- [x] **Module 7** — Checkout, orders, payments (abstract gateway layer)
- [x] **Module 8** — Customer dashboard, order history, tracking, reviews
- [ ] Module 9 — Admin dashboard & analytics
- [ ] Module 10 — Admin catalog/order/customer management
- [ ] Module 11 — Inventory, suppliers, purchase orders
- [ ] Module 12 — Reports & exports (PDF/Excel/CSV)
- [ ] Module 13 — Blog, testimonials, static pages, SEO, sitemap
- [ ] Module 14 — Security hardening pass & final QA

See the repo's task list / commit history for progress on each.
