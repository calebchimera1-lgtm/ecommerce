# Module 19 — Public Vendor Storefronts

## 1. Explanation

Every vendor has had a `store_name`, `slug`, `logo`, and `description`
since Module 15 - fields that existed for exactly this purpose but had
no public page to appear on. A customer could buy a vendor's product
(and, since Module 18, see "Sold by X" on their receipt) but had no
way to browse everything else that vendor sells. This module closes
that gap: a public storefront page per vendor at `/store/{slug}`, and
"Sold by X" links from the product page and order history into it.

### The storefront page is "the shop, scoped to one seller" - not a new implementation

`Customer\VendorStorefrontController` doesn't reimplement product
listing - it calls the exact same `Product::publicPaginate()`/
`publicCount()` that `Customer\ShopController` already uses for the
main shop grid and category/brand pages, with one added filter:
`vendor_id`. This means a vendor's storefront automatically inherits
every existing public-visibility rule (active, approved, not deleted)
without this module having to know or re-implement any of them, and
any future change to what counts as "publicly visible" only needs to
change in one place. The view reuses `partials/product-card.php` and
`partials/pagination.php` unmodified, for the same reason.

### A real gap this module found: a suspended vendor's products stayed visible

Building the storefront page's "only approved vendors get a page"
rule (`Vendor::findActiveBySlug()` requires `status = 'approved'`,
same shape as `Product::findActiveBySlug()`) surfaced a real bug in
code that predates this module: `Product::buildPublicWhere()` (and
`publicLimitedQuery()`, `findActiveBySlug()`, `allActiveForSitemap()`)
checked the *product's* `approval_status` but never the *vendor's*
`status`. A vendor suspended by admin (Module 16) kept every
already-approved product fully visible on the shop grid, product
pages, and sitemap - suspension revoked their portal access but not
their storefront presence, which defeats the point of suspending
someone.

Fixed by adding `LEFT JOIN vendors v ON v.id = p.vendor_id` and a
`(p.vendor_id IS NULL OR v.status = 'approved')` condition to all four
of those queries - platform products (`vendor_id NULL`) are
unaffected, same as every other vendor-related gate added since
Module 15. Verified live: created a live, approved product for an
approved vendor, confirmed it was visible on `/shop`, its own product
page, that vendor's new storefront page, and the sitemap; suspended
the vendor directly; confirmed the product vanished from all four
surfaces immediately; reactivated the vendor; confirmed it reappeared
everywhere. This is the same "live re-check on every request" property
`VendorMiddleware` already had for the vendor's own portal access
(Module 15) - now the storefront-facing side of a vendor's status has
it too.

### "Sold by X" becomes a link, not just a label

Module 18 added a "Sold by X" label to the customer's order-detail
page. This module makes it a link to `/store/{slug}` (added
`v.slug AS vendor_slug` to `OrderItem::forOrder()`'s existing join,
one column, no new query), and adds the same link to the product
detail page for the first time. Both reuse `$vendor['slug']` fields
that already existed - nothing new to store, just new places that
read what was already there.

## 2. Folder location / files delivered

```
app/Controllers/Customer/VendorStorefrontController.php
app/Views/customer/vendor/show.php
```

Updated: `app/Models/Vendor.php` (`findActiveBySlug()`,
`allApprovedForSitemap()`), `app/Models/Product.php` (vendor-status
guard added to every public query; `vendor_id` filter added to
`buildPublicWhere()`/`publicPaginate()`/`publicCount()`),
`app/Models/OrderItem.php` (`forOrder()` now also selects
`vendor_slug`), `app/Controllers/Customer/SitemapController.php`
(vendor storefront URLs), `app/Views/customer/product/show.php`
("Sold by X" link), `app/Views/customer/account/orders/show.php`
("Sold by X" now links), `app/Views/vendor/profile/index.php` ("View
Public Storefront" link, shown only once the vendor is `approved`),
`routes/web.php` (`/store/{slug}`).

## 3. Routes

```
GET /store/{slug}    public vendor storefront - 404 unless the vendor is 'approved'
```

## 4. SQL

None. This module is entirely application code on top of Module 15's
existing `vendors` schema.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the
actual running app:

1. `GET /store/{approved-vendor-slug}` → 200, showing the vendor's
   logo (or a placeholder icon when none is set), store name,
   description, "selling since" date derived from `approved_at`, an
   accurate product count, and a grid of their live products using
   the same product card as the main shop.
2. Confirmed sort (`?sort=price_desc` etc.) and pagination both work
   on the storefront page, identical behavior to the main shop grid
   since they share the same underlying query.
3. `GET /store/{rejected-vendor-slug}` → 404. `GET /store/{unknown-slug}` → 404.
   Only `status = 'approved'` vendors get a reachable page.
4. **The suspension gap** (see above) - reproduced the bug against a
   fresh live product, confirmed the fix resolves it across all four
   surfaces (shop grid, product detail, the vendor's own storefront,
   sitemap.xml), and confirmed reactivating the vendor immediately
   restores visibility everywhere with no other action needed.
5. Product detail page for a vendor-owned product shows a "Sold by
   Milano Leather Goods" link pointing at `/store/milano-leather-goods`;
   a platform-owned product's detail page shows no such line at all.
6. Customer's own order-detail page: the previously-plain "Sold by X"
   text (Module 18) is now a working link to the same storefront URL.
7. A vendor's own `/vendor/profile` page shows a "View Public
   Storefront" link pointing at their real `/store/{slug}` URL - only
   while their account status is `approved` (verified absent while a
   test account's status was `pending`/`rejected`/`suspended` by
   inspecting the same conditional against each status directly).
8. `/sitemap.xml` includes `/store/{slug}` for every approved vendor
   and excludes it the moment a vendor is suspended (re-verified
   during the suspension-gap test above).
9. Full regression sweep: home, shop, individual product pages,
   sitemap, and the full admin panel (dashboard, orders, products,
   vendors, payouts) all still 200 for the right sessions; every
   new/changed file passes `php -l` with zero syntax errors.

## 6. Bugs found while testing (and fixed before commit)

One real, pre-existing bug - described in full above: a suspended
vendor's already-approved products remained visible across the shop
grid, product detail pages, and the sitemap, because none of
`Product`'s public-facing queries checked the selling vendor's own
`status`, only the product's own `approval_status`. This predates
Module 19 (it existed as soon as Module 16 added vendor suspension)
but only became visibly relevant once this module needed the same
guarantee for the storefront page itself, at which point building that
guarantee correctly meant fixing it everywhere at once rather than
leaving the storefront page more strict than the rest of the site.
Fixed in `Product::buildPublicWhere()`, `publicLimitedQuery()`,
`findActiveBySlug()`, and `allActiveForSitemap()` (see explanation
above); verified via the suspend/reactivate test in section 5.

No other bugs. Testing again hit the same PHP-built-in-dev-server
quirk documented in every module since Module 10 (first POST after a
fresh cookie jar occasionally 419s, always succeeds on immediate
retry; also observed session cookies going stale across the sandbox's
periodic service restarts mid-session, requiring a fresh login - both
harness artifacts, not application bugs).

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `/store/{slug}` 404s for a vendor you know is approved | Slug typo, or the vendor's `status` isn't actually `approved` (check the exact value, not just "not rejected") | `SELECT status FROM vendors WHERE slug = '...'` - only `approved` renders |
| A vendor's storefront shows fewer products than their `/vendor/products` list | Expected - the storefront only shows `is_active = 1 AND approval_status = 'approved'` items, same as the main shop; a vendor's own product list (Module 17) shows every status | Compare against `/vendor/products?approval_status=approved` filtered the same way |
| A product or vendor storefront disappears from the public site with no obvious cause | The selling vendor was suspended (or the vendor row's `status` otherwise isn't `approved`) - this is now enforced everywhere per the bug fix above | `SELECT status FROM vendors WHERE id = (SELECT vendor_id FROM products WHERE id = ...)`; reactivating the vendor restores visibility immediately, no other step needed |
| "Sold by X" link on an order or product page goes to a 404 | Should not happen - the link only renders when `vendor_slug` is present, and it's sourced from the same `vendors.slug` `Vendor::findActiveBySlug()` checks | If it does happen, the vendor's status likely changed to non-approved after the page was cached/rendered; a reload reflects current status |

## 8. Next module

None currently planned - this closes out the marketplace-visibility
gap left after Modules 15-18. Future candidates discussed but not
scheduled: a vendor-side sales/analytics dashboard (the vendor
counterpart to Module 9), and vendor ratings/reviews distinct from
product reviews.
