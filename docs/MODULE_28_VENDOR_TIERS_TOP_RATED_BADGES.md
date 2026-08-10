# Module 28 — Vendor Tiers / Top Rated Seller Badges

## 1. Explanation

Module 21 gave vendors real customer ratings (`vendor_reviews`,
moderated the same way `product_reviews` already was) but nothing in
the app ever used that data beyond showing a star average on a
vendor's own storefront page. This module classifies vendors into a
tier from that same rating data and surfaces a badge wherever a
shopper might decide who to buy from.

### Thresholds require both a minimum count and a minimum average

`Vendor::tierLabel(int $reviewCount, float $averageRating): ?string`
is a small pure function (two constants pairs, no DB access itself):

- **Top Rated Seller**: `reviewCount >= 5 AND averageRating >= 4.5`
- **Rising Seller**: `reviewCount >= 1 AND averageRating >= 4.0`
- Otherwise: no badge (`null`)

Both conditions matter independently on purpose. A count-only
threshold would let a vendor with dozens of mediocre 3-star reviews
earn "Top Rated" just by sheer volume; an average-only threshold would
let a brand-new vendor with a single 5-star review from a friend look
identical to an established seller with fifty. Requiring both is a
small but real anti-gaming decision - verified directly in
`tests/Feature/Models/VendorTest.php` (`test_tier_label_requires_both_a_minimum_review_count_and_average`)
and confirmed live: a single 5.0-average review renders "Rising
Seller," not "Top Rated Seller," even though 5.0 clears the Top Rated
*average* bar on its own.

### No new column, no recompute job - always read live

There's no `vendors.tier` column and no scheduled job to keep one in
sync. Tier is computed on every read from the vendor's *current*
approved review count/average, the same way `TaxRate::forAddress()`
and `ReturnRequestItem::activeQuantityForOrderItem()` compute their
answers live rather than maintaining a denormalized copy that could
drift. The one place this matters for query cost is the admin vendor
list, which shows every vendor's tier at once: `Vendor::paginateAdmin()`
and `Vendor::findWithUser()` both now select two correlated subqueries
(`RATING_SUBQUERIES`, mirroring `Product::PRIMARY_IMAGE_SUBQUERY`'s
existing pattern) directly in the row query instead of running a
separate `VendorReview::ratingSummary()` call per row - avoiding an
N+1 query pattern on a page that could list many vendors.

### Where badges appear (and where they deliberately don't)

- The vendor's own storefront page (`customer/vendor/show.php`), next
  to the store name - the natural home for a seller's own badge.
- The product detail page's "Sold by X" line
  (`customer/product/show.php`) - a shopper deciding whether to trust
  an unfamiliar seller on the exact page where that decision happens.
- The admin vendor list and detail pages, for staff visibility.

**Deliberately not added to `partials/product-card.php`** (the shop
grid / category / related-products thumbnail used everywhere across
the storefront). Doing that properly means joining vendor rating
aggregates into `Product::publicPaginate()`'s query - the single
highest-traffic query in the app, already carrying a category/brand
join and a primary-image subquery. That's a real, separate piece of
work with its own performance tradeoffs, not something to bolt onto a
badge feature; scoped out rather than done partially. The product
*detail* page (where a shopper who's already interested in one
specific item decides whether to trust its seller) captures the
actual decision point without touching the listing grid.

## 2. Files

- `app/Models/Vendor.php` — `tierLabel()`, the `RATING_SUBQUERIES`
  constant, and `findWithUser()`/`paginateAdmin()` updated to select
  `review_count`/`average_rating` per row.
- `app/Controllers/Customer/VendorStorefrontController.php` — computes
  and passes `tier` to the storefront view.
- `app/Controllers/Customer/ProductController.php` — computes the
  selling vendor's tier (when the product has one) and passes
  `vendorTier` to the product detail view.
- `app/Controllers/Admin/VendorController.php` — computes `tier` per
  row for the vendor list, and for the single-vendor detail page.
- `app/Views/customer/vendor/show.php`, `app/Views/customer/product/show.php`,
  `app/Views/admin/vendors/index.php`, `app/Views/admin/vendors/show.php` —
  badge rendering.
- `tests/Feature/Models/VendorTest.php` — 4 new tests: threshold
  requires both count and average, boundary values at exactly 5/4.5
  and 1/4.0, and `findWithUser()`'s aggregate correctly excludes
  pending (unapproved) reviews.

## 3. How to test

1. As a customer with a delivered order from a vendor, submit a review
   at `/store/{vendor-slug}` (5 stars, say).
2. As an admin, approve it at `/admin/vendor-reviews`.
3. Revisit the vendor's storefront - "Rising Seller" appears (one
   review clears the average bar but not the count bar for Top
   Rated). Add four more approved 5-star (or high) reviews from
   different customers and the same vendor becomes "Top Rated Seller"
   everywhere the badge is shown.
4. A vendor with zero approved reviews shows no badge anywhere,
   including a plain "—" in the admin vendor list's Tier column.

## 4. Live verification performed

- Submitted a real vendor review through the actual customer UI,
  confirmed no badge appeared anywhere while it was still pending
  admin approval (`ratingSummary()`/the rating subqueries only ever
  count `is_approved = 1` rows).
- Approved it as admin and confirmed "Rising Seller" appeared
  correctly on all four surfaces: the vendor's own storefront, the
  "Sold by" line on one of that vendor's product detail pages, the
  admin vendor list's new Tier column, and the admin vendor detail
  page (which also now shows the raw average/count, e.g. "5.0 avg ·
  1 review").
- Added four more approved reviews (distinct reviewers, averaging the
  vendor to 4.8 across 5 reviews) and confirmed all four surfaces
  correctly upgraded to "Top Rated Seller".
- Confirmed a vendor with zero reviews shows no badge on its
  storefront or any product page, and shows the "—" placeholder (not
  a blank cell, not an error) in the admin vendor list.
- Ran the full PHPUnit suite three times in a row (86 tests, up from
  83, identical results each run), `composer lint`, and `composer cs`
  (0 errors/warnings).

## 5. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| A vendor with several 5-star reviews still shows no badge | Fewer than 5 approved reviews - "Top Rated Seller" requires both `reviewCount >= 5` and `average >= 4.5`; with 1-4 reviews the vendor tops out at "Rising Seller" (if the average clears 4.0) | Not a bug - working as designed (section 1); the minimum-sample-size requirement is intentional |
| A review the customer submitted doesn't seem to affect the vendor's tier at all | The review is still `is_approved = 0` (pending admin moderation) - both `VendorReview::ratingSummary()` and the new `RATING_SUBQUERIES` only ever count approved reviews | Approve it at `/admin/vendor-reviews` |
| The shop grid / category pages never show a "Top Rated Seller" badge on product thumbnails | Deliberately out of scope (section 1) - only the vendor storefront and the product *detail* page show the badge | Not a bug; extending this to the grid requires joining rating aggregates into `Product::publicPaginate()`, a separate, larger piece of work |

## 6. Next module

**Module 29 — Caching layer for storefront/dashboard queries**: the
increasingly heavy read queries this session has been adding
(`Product::publicPaginate()`'s joins/subqueries, `Vendor::paginateAdmin()`'s
new rating subqueries, admin dashboard analytics) have no caching at
all - every request recomputes everything from scratch. Real scope:
cache the genuinely expensive, slow-changing reads (category trees,
homepage featured products, admin dashboard aggregates) with a clear,
short invalidation story, without introducing staleness bugs in
anything that must always be live (stock levels, order status, cart
contents).
