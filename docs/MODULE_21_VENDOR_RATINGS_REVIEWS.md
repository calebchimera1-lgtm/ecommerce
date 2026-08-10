# Module 21 — Vendor Ratings & Reviews

## 1. Explanation

The last discussed marketplace candidate: letting a customer rate a
vendor as a seller, not just the products they bought. Product
reviews (Module 8) already answer "was this item good"; this module
answers "was this seller good to buy from" - packaging, accuracy,
communication - shown as a star rating and review list on the
vendor's public storefront (Module 19).

### A verified-purchase gate product reviews never had

Module 8's product reviews let any logged-in customer review any
product once - no purchase check. This module is deliberately
stricter: `VendorOrder::hasDeliveredOrderForUser()` requires at least
one `vendor_orders` row for that vendor, belonging to an order this
customer placed, with `status = 'delivered'` before the review form
even renders. The proposal that scoped this module explicitly said
"after a delivered order" - a vendor's reputation is more
consequential to gate loosely than a single product's, since it
follows them across their entire catalog, so this module doesn't
default to product reviews' looser rule just because it was
available. `VendorReview::userHasReviewed()` still caps it at one
review per customer per vendor (mirroring product reviews' one-per-
product cap), enforced twice: in the form-rendering gate
(`canReview`) and again in `storeReview()` itself, the same
belt-and-suspenders shape `Customer\ProductController::storeReview()`
already used.

### Everything else deliberately mirrors ProductReview

`VendorReview` (model), the moderation queue, and the customer-facing
submit/edit/delete flow all copy `ProductReview`'s exact shape:
`is_approved` starts at `0` and requires admin approval before a
review is public; editing an already-approved review resets it to
`0` (Module 8's reasoning applies unchanged - a customer shouldn't be
able to slip different content past a review that was already
vetted); one review per reviewer, enforced with a real
`UNIQUE (vendor_id, user_id)` constraint, not just an application
check. Reusing an already-proven shape rather than inventing a new one
means the moderation workflow behaves exactly the way admin already
expects reviews to behave, and needed no new documentation to explain.

### Kept as parallel systems, not merged into one

`VendorReview` is a separate table and controller from `ProductReview`,
not a `reviewable_type`/`reviewable_id` polymorphic table covering
both. This matches Module 18's `PayoutController` reasoning (kept
separate from `VendorController` despite both being "about a vendor")
extended one step further: a vendor review and a product review are
different entities under moderation, reviewed by admin for different
reasons, and shown on different pages (a vendor's storefront vs. a
product's detail page) - collapsing them into one polymorphic table
would save a small amount of schema at the cost of every query needing
a type filter it doesn't today. The admin nav gets its own "Vendor
Reviews" entry alongside the existing "Reviews" (products), reusing
the same `reviews.manage` permission since moderating either is the
same trust tier.

The one place they *do* merge is presentation: a customer's "My
Reviews" page (`Customer\ReviewController::index()`) now shows both a
"Product Reviews" and a "Store Reviews" section, since from a
customer's point of view "everything I've reviewed" is naturally one
place, even though the two models underneath stay independent.

## 2. Folder location / files delivered

```
app/Models/VendorReview.php
app/Controllers/Admin/VendorReviewController.php
app/Views/admin/vendor-reviews/index.php
app/Views/customer/account/reviews/edit-vendor.php
database/migrations/0007_add_vendor_reviews.sql
```

Updated: `app/Models/VendorOrder.php` (`hasDeliveredOrderForUser()`),
`app/Controllers/Customer/VendorStorefrontController.php` (review
data on `show()`, new `storeReview()`), `app/Controllers/Customer/ReviewController.php`
(`editVendor`/`updateVendor`/`destroyVendor`, vendor reviews on
`index()`), `app/Views/customer/vendor/show.php` (rating summary,
reviews list, submit form), `app/Views/customer/account/reviews/index.php`
(Store Reviews section), `app/Views/admin/layouts/app.php` (Vendor
Reviews nav entry), `routes/web.php` / `routes/admin.php` (new
routes), `database/kymera_collection.sql` (`vendor_reviews` table).

## 3. Routes

```
POST /store/{slug}/reviews                                    AuthMiddleware
GET  /account/reviews/vendor/{id}/edit                         AuthMiddleware (ownership-checked)
POST /account/reviews/vendor/{id}                               AuthMiddleware (ownership-checked)
POST /account/reviews/vendor/{id}/delete                        AuthMiddleware (ownership-checked)

GET  /admin/vendor-reviews                                      reviews.manage
POST /admin/vendor-reviews/{id}/approve                         reviews.manage
POST /admin/vendor-reviews/{id}/delete                          reviews.manage
```

## 4. SQL

`database/migrations/0007_add_vendor_reviews.sql`:

- New `vendor_reviews` table: `vendor_id`, `user_id`,
  `vendor_order_id` (nullable, traceability only), `rating` (1-5,
  `CHECK` constraint mirroring `product_reviews`), `title`, `comment`,
  `is_approved`. `UNIQUE (vendor_id, user_id)` enforces one review per
  customer per vendor at the database level.

Applied to `kymera_collection.sql` directly for fresh installs, and
via this migration for databases seeded before it.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the
actual running app:

1. **Purchase gate blocks correctly**: as a customer with a
   vendor_orders row still at `status = 'shipped'` (not yet
   `delivered`) for a given vendor, the storefront page correctly
   showed "You can review this store once an order from them has been
   delivered to you" instead of the review form.
2. **Gate opens on delivery**: updated that same sub-order's status to
   `delivered` - confirmed the review form now renders, submitted a
   real review (rating 5, title, comment) through it - confirmed a
   `vendor_reviews` row with `is_approved = 0`.
3. **Duplicate blocked**: attempting to submit a second review for the
   same vendor as the same customer was silently redirected without
   creating a second row (`SELECT COUNT(*)` stayed at 1) - the
   application-level `userHasReviewed()` check.
4. **Pending review invisible publicly**: the unapproved review did
   not appear on the storefront's review list or affect the rating
   average.
5. **My Reviews page**: showed the pending review under a new "Store
   Reviews" section with a "Pending approval" badge, alongside the
   existing "Product Reviews" section (confirmed unaffected/still
   working).
6. **Admin moderation**: `/admin/vendor-reviews?status=pending` showed
   the review with the correct vendor name and customer name.
   Approved it - confirmed `is_approved → 1`, an `audit_logs` row
   (`vendor_review.approved`), and the review now appearing publicly
   on the storefront with the correct rating average ("5 (1 review)").
7. **Edit resubmits for review**: edited the now-approved review
   (changed rating and text) from `/account/reviews/vendor/{id}/edit` -
   confirmed `is_approved` reset to `0` and the review immediately
   disappeared from the public storefront again, exactly like
   product-review editing already worked.
8. **Cross-user ownership isolation**: a different logged-in user
   (admin's own customer session) requesting another customer's
   vendor-review edit page got a 404 - `ownedVendorReview()` scopes by
   `user_id`, mirroring the existing product-review ownership check
   exactly.
9. **Delete (both paths)**: the owning customer deleted their own
   review via `/account/reviews/vendor/{id}/delete` - confirmed
   removed. Submitted a fresh review and removed it via admin's
   "Remove" action instead - confirmed removed with an `audit_logs`
   row (`vendor_review.deleted`).
10. Full regression sweep: home, shop, product pages, the vendor
    storefront page, the full admin panel (dashboard, both review
    queues), and "My Reviews" all still 200 for the right sessions; an
    unauthenticated POST to `/store/{slug}/reviews` and a GET to
    `/admin/vendor-reviews` both redirect rather than exposing
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
| "You can review this store once an order from them has been delivered to you" despite having ordered from them | The relevant `vendor_orders` row's `status` isn't `delivered` yet, or the order was placed before Module 18 existed (no split, no `vendor_orders` row at all) | `SELECT status FROM vendor_orders WHERE vendor_id = ... AND order_id IN (SELECT id FROM orders WHERE user_id = ...)`; only a `delivered` status satisfies the gate |
| A submitted review never appears on the storefront | Expected until an admin approves it - `is_approved` starts at `0` | Check `/admin/vendor-reviews?status=pending`; approve it there |
| A previously-visible review disappears after the customer "just fixed a typo" | Expected - any edit resets `is_approved` to `0`, same as product reviews | The customer (or admin) needs to re-approve; this is not a bug to route around |
| 404 editing/deleting a vendor review that clearly exists | The review's `user_id` doesn't match the logged-in customer | Expected and intentional - `ownedVendorReview()` scopes every customer-side review action to the caller's own reviews, mirroring product-review ownership |
| Duplicate-review attempt succeeds and creates two rows | Should not happen - both the application check (`userHasReviewed()`) and the database's `UNIQUE (vendor_id, user_id)` constraint block it | If it somehow occurs, check whether the unique constraint was dropped or the migration wasn't applied (`SHOW CREATE TABLE vendor_reviews`) |

## 8. Next module

None currently planned. This was the last discussed candidate from
the marketplace extension's follow-up list (Modules 19-21).
