# Module 5 — Storefront (Home, Shop, Product Detail, Search)

## 1. Explanation

The public, customer-facing site that consumes the catalog Module 4's
admin lets staff build: a real homepage, category/brand/search
browsing with filters and sort, product detail pages with a gallery,
variants, specifications, and a working review system, plus the static
pages (About, Contact, FAQs, Privacy, Terms) and newsletter signup the
site's footer needs to avoid dead links.

**Out of scope, by design, per the build roadmap:** the "Add to Cart"
button is present but intentionally disabled with an honest label
("Coming Soon") - cart/wishlist are Module 6. Blog posts and an
Instagram gallery are deferred to Module 13 (blog/content), since
there's no blog admin yet to populate them; building a "Latest Blog
Posts" section against an empty table, or an Instagram section with no
real integration, would just be decorative filler.

### Homepage sections and their data sources

| Brief's section | Implementation |
|---|---|
| Hero Slider | Static hero (copy/CTA) - no slider library needed for one slide; revisit if multiple hero slides are wanted later |
| Featured Categories / Luxury Collections | `Category::activeOrdered()` - merged into one "Shop by Category" section rather than two near-duplicates |
| New Arrivals | `Product::newArrivals()` - newest by `created_at` |
| Trending Products / Best Sellers | `Product::trending()` - ordered by `view_count DESC`. Honestly labeled "Trending Now" rather than "Best Sellers," since there's no order data yet to compute real sales; `view_count` is a real, incrementing metric (see below), not a placeholder |
| Flash Sales | `Product::onSale()` - any product with `sale_price` set |
| Featured Products | `Product::featured()` - `is_featured = 1` |
| Testimonials | `Testimonial::activeOrdered()` - table existed since Module 1 with no admin CRUD yet, so seeded via `database/seeders/testimonials.sql` (optional, run separately from the main schema) |
| Newsletter | Real, functional - posts to `/newsletter/subscribe`, inserts into `newsletter_subscribers` |
| Latest Blog Posts, Instagram Gallery | Deferred to Module 13 |

`view_count` becomes a genuinely meaningful "trending" signal over time
because `ProductController::show()` increments it
(`Product::incrementViewCount()`) on every product page visit.

### Shop / category / brand / search: one listing renderer

`ShopController` has four public entry points (`index`, `category`,
`brand`, `search`) that all funnel into a single private
`renderListing()`, which builds a `$filters` array (search term,
category, brand, price range), fetches
`Product::publicPaginate()`/`publicCount()`, and renders one shared
view (`customer/shop/index`). Category/brand pages pre-fill their
respective filter via `$baseFilters`, which take precedence over the
query string with `??` - avoiding the classic bug of a hardcoded
`array_merge()` order accidentally letting an empty query parameter
overwrite the route's own filter (a bug caught during design, not
after - see the code comment in `ShopController::renderListing()`).

All public product queries (`publicPaginate`, `publicCount`,
`findActiveBySlug`, `featured`, `newArrivals`, `onSale`, `trending`)
restrict to `deleted_at IS NULL AND is_active = 1`, unlike the admin
queries from Module 4 which show every non-deleted product regardless
of active status. They also each resolve a representative image via a
correlated subquery (`(SELECT image_path FROM product_images WHERE
product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC
LIMIT 1)`), which prefers the primary image but falls back to the
first image in sort order - safer than a `LEFT JOIN ... is_primary = 1`
which could return duplicate product rows if that invariant were ever
violated.

### Product detail page

- **Gallery**: thumbnail click swaps the main image
  (`public/assets/js/product-gallery.js`, vanilla JS); clicking the
  main image toggles a CSS zoom class - satisfies "zoom effect" and
  "gallery" from the product feature list without a JS library.
- **Variants**: `product_attributes` rows grouped by `attribute_name`
  (Color, Size, ...) and rendered as pill badges under each group
  heading.
- **Specifications**: the JSON blob from Module 4 decoded into a
  simple key/value table.
- **Reviews**: `ProductReview::approvedForProduct()` shows only
  `is_approved = 1` rows publicly. A logged-in customer who hasn't yet
  reviewed this product (`ProductReview::userHasReviewed()`) sees a
  review form; submissions are created with `is_approved = 0` and
  don't appear until an admin approves them (moderation UI is a later
  Reviews-management module - for now, approval is a direct DB update,
  same as the admin `settings` table in Module 3 before its own admin
  UI existed).
- **Related products**: same category, excluding the current product,
  newest first, capped at 4.

## 2. Folder location / files delivered

```
app/Controllers/Customer/ShopController.php
app/Controllers/Customer/ProductController.php       (public product detail + reviews)
app/Controllers/Customer/StaticController.php         (about/contact/faqs/privacy/terms)
app/Controllers/Customer/NewsletterController.php
app/Controllers/Customer/HomeController.php           (updated: real homepage data)
app/Models/ProductReview.php
app/Models/Testimonial.php
app/Models/ContactMessage.php
app/Models/NewsletterSubscriber.php
app/Models/Product.php        (updated: public query methods, view_count increment)
app/Models/Category.php       (updated: activeOrdered(), findActiveBySlug())
app/Models/Brand.php          (updated: activeOrdered(), findActiveBySlug())
app/Views/customer/layouts/site.php    (sticky nav + mega menu + footer + newsletter)
app/Views/customer/home/index.php      (replaces the Module 1 placeholder)
app/Views/customer/shop/index.php
app/Views/customer/product/show.php
app/Views/customer/static/{about,contact,faqs,privacy,terms}.php
app/Views/partials/product-card.php
public/assets/css/site.css             (storefront theme - first real stylesheet; earlier auth pages used inline styles)
public/assets/js/product-gallery.js
database/seeders/testimonials.sql      (optional demo content)
routes/web.php                          (updated: storefront routes)
```

## 3. Routes

| Method | Path | Notes |
|---|---|---|
| GET | `/` | Homepage |
| GET | `/shop`, `/shop/category/{slug}`, `/shop/brand/{slug}`, `/search` | All render the same listing view with different pre-filters |
| GET | `/product/{slug}` | Product detail; increments `view_count` |
| POST | `/product/{slug}/reviews` | Auth + CSRF required |
| GET | `/about`, `/faqs`, `/privacy-policy`, `/terms` | Static content |
| GET/POST | `/contact` | Real contact form, stored in `contact_messages` |
| POST | `/newsletter/subscribe` | CSRF required, idempotent on duplicate email |

## 4. SQL

No schema changes. `database/seeders/testimonials.sql` is new -
optional demo rows for the `testimonials` table (already defined in
Module 1's schema, previously unused).

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance with actual seeded
products (created through the Module 4 admin panel, including real
uploaded images):

1. Log in as Super Admin, create a brand and 3-4 products across
   different categories, with at least one on sale (`sale_price` set)
   and at least one marked "Featured."
2. **Homepage**: confirm New Arrivals, Flash Sale, Featured Products,
   and Trending sections all show the right products, and that a
   discount badge appears on sale items.
3. **Shop/category/brand**: `/shop`, `/shop/category/{slug}`,
   `/shop/brand/{slug}` all filter correctly; combine with
   `?min_price=`/`?max_price=`/`?sort=` and confirm results and order
   change accordingly.
4. **Search**: `/search?q=<term>` matches on name/short description;
   a query with no matches shows "No products match your search."
5. **Product detail**: gallery thumbnails swap the main image, variant
   pills render grouped by attribute name, specifications table
   matches what was entered in the admin form, and `view_count`
   increments in the DB on each visit.
6. **Reviews**: log in as a customer, submit a review on a product you
   haven't reviewed → it does *not* appear publicly yet
   (`is_approved = 0`). Approve it directly in the DB
   (`UPDATE product_reviews SET is_approved = 1 WHERE id = ...`) →
   it now appears, and the rating summary updates. Revisit the product
   as the same customer → the review form is gone (already reviewed).
7. **Static pages + contact + newsletter**: all five static pages
   return 200; submitting the contact form creates a row in
   `contact_messages`; subscribing to the newsletter creates a row in
   `newsletter_subscribers`, and subscribing the same email twice
   doesn't error (idempotent, generic success message either way).

## 6. Bugs found while testing (and fixed before commit)

| Bug | File | Symptom | Fix |
|---|---|---|---|
| Same named placeholder (`:search`) used twice in one SQL string | `app/Models/Product.php` (`buildPublicWhere()`, and `buildFilterWhere()` from **Module 4**, which had never actually been exercised with a search term until this module's testing) | `/search?q=...` and the admin product search box both threw `PDOException: SQLSTATE[HY093]: Invalid parameter number` - PDO's native (non-emulated) MySQL prepared statements reject reusing one named parameter for two placeholders in the same query | Split into two distinct placeholders (`:search_name`/`:search_sku` for admin, `:search_name`/`:search_desc` for public), each bound to the same value |

This is the first defect this build has found that was actually
introduced in an *earlier, already-committed* module (Module 4's admin
product search) rather than the current one - it went uncaught there
because that module's testing exercised the category/brand filters and
pagination but never actually typed a search term into the box. Worth
noting as a reminder that "tested end-to-end" only covers the paths
actually exercised.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `PDOException: SQLSTATE[HY093]: Invalid parameter number` on any filtered query | Reusing the same named PDO placeholder twice in one query string, under `PDO::ATTR_EMULATE_PREPARES => false` (this app's default) | Give each occurrence its own placeholder name, bound to the same value - never write `:x ... :x` twice in a single SQL string in this codebase |
| Homepage sections are empty | No products/categories/testimonials exist yet, or none are marked `is_active`/`is_featured` | Create catalog data via the admin panel (Module 4); run `database/seeders/testimonials.sql` for sample testimonials |
| "You May Also Like" section never appears | Only one active product exists in that category | Expected - the section only renders when there's at least one other product to relate to |
| Review form missing for a logged-in customer | They've already reviewed this product (one review per user per product, enforced in `ProductController::storeReview()`) | Expected; not a bug |

## 8. Next module

**Module 6 — Cart & wishlist**: add to cart, quantity updates, coupon
codes, shipping/tax calculation on the cart summary, saved carts for
logged-in users, and wishlist add/remove - which is exactly what will
turn the storefront's disabled "Add to Cart" button into a real one.
Waiting for confirmation to proceed.
