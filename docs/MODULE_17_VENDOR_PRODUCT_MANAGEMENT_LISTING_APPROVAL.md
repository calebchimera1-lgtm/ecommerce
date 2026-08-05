# Module 17 — Vendor Product Management & Listing Approval

## 1. Explanation

Modules 15-16 built a vendor who can log in and edit a store profile,
but has nothing to sell yet. This module gives vendors their own
product catalog - built on the exact same `products` table, images,
attributes, and specifications every platform product already uses -
scoped so a vendor can only ever see or touch their own rows, and
gated so nothing they submit reaches the storefront until an admin
approves it. Order splitting, commission, and payouts are Module 18.

### Every vendor mutation is ownership-scoped, not permission-scoped

`Vendor\ProductController` never trusts an `{id}` in a URL on its own
- `Product::findForVendor($id, $vendorId)` requires both the id *and*
the calling vendor's own id to match, and every action (`edit`,
`update`, `destroy`, `deleteImage`, `setPrimaryImage`) routes through
it before touching a row. This is a different shape of access control
than `Admin\ProductController`'s `products.manage` permission check -
an admin either can or can't manage products, full stop; a vendor can
always manage products, just never any product that isn't theirs.
Verified live: a second vendor given another vendor's real product id
gets a 404 on edit, on delete, and on both image actions - not a
redirect, not an empty form, a flat "this doesn't exist" exactly as
it should look from outside.

### Any content edit resubmits a listing for review; stock updates don't

Module 15's decision - admin approves every vendor listing before it
goes live - has an obvious follow-up question: does *editing* an
already-approved listing require re-approval? This module's answer is
yes, deliberately: `store()` and `update()` both force
`approval_status = 'pending'` (clearing any prior `rejection_reason`),
because a changed listing is unreviewed content again by definition -
"approved" described what admin actually looked at, not the product
row's current contents.

That would make routine stock updates painful (a vendor keeping
inventory current, once a day, every day, would yank their own live
listing back into the review queue each time) - so `restock()` is a
narrow, separate action that updates only `stock_quantity` and
touches nothing else, mirroring the split Module 10 already
established for the admin side (`Product::adjustStock()` as a
dedicated inventory mutation distinct from the full edit form). It's
reachable straight from the product list as an inline field, not
buried in the full edit form, since it's meant to be the fast path.
Verified live: restocking an approved listing changed
`stock_quantity` and left `approval_status = 'approved'` untouched;
a full edit save on the same listing reset it to `pending` and pulled
it off the storefront immediately.

### What vendors can't set on their own listings, and why

The vendor form deliberately omits three fields the admin form has:

- **Supplier** - suppliers are the platform's own restocking
  relationships for platform-owned inventory; a vendor's product isn't
  sourced through them, so `supplier_id` is always `NULL` for a
  vendor-owned row.
- **Featured** - homepage/featured placement is platform curation, a
  marketing decision, not something a vendor should be able to grant
  themselves; `is_featured` is always `0` on creation and isn't
  exposed as an editable field in the vendor form at all (an admin
  can still feature a vendor's product from the admin product form,
  same as any other product).
- **Brand** - left off for the same reason as supplier: brands in
  this catalog represent curated platform relationships, not
  something a vendor assigns to their own goods.

`is_active` stays vendor-controlled (do *I* want this listed) as a
second, independent gate alongside `approval_status` (is this
*allowed* to be listed) - the storefront only shows a product where
both are true, exactly like `is_active` already worked pre-Module-15,
now with one more condition ANDed in.

### The commission rate is shown, not just stored

Module 15 added `categories.commission_rate` and a platform-wide
`default_commission_rate` fallback but nothing read either value yet.
This module's vendor product form annotates every category option
with its effective rate (`categories.commission_rate` if set,
otherwise the default) - `Vendor\ProductController::categoriesWithCommission()`
computes it once per form load. A vendor choosing where to list a
product can now see what they'll actually net before they submit,
rather than discovering it later. The commission *math itself*
(applying that rate to a sale) is still Module 18's job - this module
only surfaces the number that already existed in the schema.

### The approval queue lives inside the existing product list, not a new page

Rather than build a separate "vendor approval queue" screen,
`/admin/products` (already the single place staff manage every
product) gained a Vendor column, an Approval-status badge, and an
`approval_status` filter - and the existing edit page gained an
Approve/Reject action block, shown only when `vendor_id IS NOT NULL`.
Platform products (`vendor_id NULL`) render exactly as before, no
badge, no actions - there's nothing to approve about a product staff
created directly. This reuses `products.manage` (granted to Manager
and Super Admin) rather than introducing a new permission, since
reviewing a listing's content is the same trust tier as editing any
other product - unlike Module 16's `vendors.manage` (Super-Admin-only),
which gates a different kind of decision: who gets an account at all.

Reject requires a reason (`required|max:255`, identical validation to
Module 16's vendor-rejection reason) and is only offered from
`pending`; approve is offered from `pending` *or* `rejected`, since an
admin can change their mind on a listing without the vendor needing to
resubmit first. Both actions are audit-logged
(`product.approved`/`product.rejected`) and email the vendor -
matching Module 16's pattern of pairing every admin-initiated status
change on someone else's account/listing with both a paper trail and
a notification.

## 2. Folder location / files delivered

```
app/Controllers/Vendor/ProductController.php
app/Views/vendor/products/{index,form}.php
app/Views/emails/product-{approved,rejected}.php
database/migrations/0005_add_product_rejection_reason.sql
```

Updated: `app/Models/Product.php` (storefront queries gated by
`approval_status = 'approved'`; vendor-scoped pagination/lookup;
`approveListing()`/`rejectListing()`; admin list vendor join +
`approval_status` filter), `app/Controllers/Admin/ProductController.php`
(`approve`/`reject` actions), `app/Controllers/Vendor/DashboardController.php`
(product status counts), `app/Views/admin/products/{index,form}.php`
(vendor column, approval badge/filter, approve/reject UI),
`app/Views/vendor/{layouts/app,dashboard/index}.php` (My Products nav
entry, product stat tiles), `routes/vendor.php` (product routes),
`routes/admin.php` (approve/reject routes), `database/kymera_collection.sql`
(`products.rejection_reason`).

## 3. Routes

```
GET  /vendor/products                                            VendorMiddleware
GET  /vendor/products/create                                     VendorMiddleware
POST /vendor/products                                             VendorMiddleware
GET  /vendor/products/{id}/edit                                   VendorMiddleware (ownership-checked)
POST /vendor/products/{id}                                        VendorMiddleware (ownership-checked)
POST /vendor/products/{id}/restock                                VendorMiddleware (ownership-checked)
POST /vendor/products/{id}/delete                                 VendorMiddleware (ownership-checked)
POST /vendor/products/{id}/images/{imageId}/delete                VendorMiddleware (ownership-checked)
POST /vendor/products/{id}/images/{imageId}/primary               VendorMiddleware (ownership-checked)

POST /admin/products/{id}/approve                                 products.manage
POST /admin/products/{id}/reject                                  products.manage
```

## 4. SQL

`database/migrations/0005_add_product_rejection_reason.sql`:

- `products.rejection_reason VARCHAR(255) NULL` - mirrors
  `vendors.rejection_reason`; lets a rejected listing carry an
  explanation the way a rejected vendor application already does.

Applied to `kymera_collection.sql` directly for fresh installs, and
via this migration for databases seeded before it.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the
actual running app (vendor portal, admin panel, and public storefront
- not direct queries except to confirm results):

1. Logged in as an approved test vendor (Milano Leather Goods);
   `/vendor/products` correctly showed an empty list before any
   listing existed.
2. **Create**: submitted the real "New Product" form with a category,
   price/sale price, description, and an uploaded image - confirmed
   the resulting row has `vendor_id` set to the vendor's own id and
   `approval_status = 'pending'`, the image landed in
   `public/uploads/products/` with a `product_images` row, and the
   category dropdown's option labels showed the correct commission
   percentage for each category.
3. Confirmed the new pending listing is invisible from every public
   angle: absent from `/shop`, its product-detail URL 404s, and
   absent from `/sitemap.xml`.
4. **Admin approval queue**: logged in as admin, filtered
   `/admin/products?approval_status=pending` - the listing appeared
   with the correct vendor name in a new Vendor column and a Pending
   badge. Opened its edit page - confirmed the vendor-only approval
   banner and Approve/Reject buttons render (and are absent entirely
   on a platform-owned product's edit page).
5. **Approve**: submitted Approve - confirmed `approval_status` →
   `approved`, an `audit_logs` row (`product.approved`, old/new
   captured), an approval email logged to the vendor with the correct
   product name, and - immediately after - the product now appearing
   on `/shop`, at its detail URL (200), and in `/sitemap.xml`.
6. **Edit resubmits for review**: as the vendor, opened the
   now-approved listing's edit form (confirmed it shows "Live on the
   storefront... saving will resubmit for review"), changed the price
   and description, saved. Confirmed `approval_status` reverted to
   `pending` and the product immediately disappeared from `/shop`
   again - a live listing does not stay live through an unreviewed
   edit.
7. **Restock bypasses review**: re-approved the listing, then used
   the vendor product list's inline restock field to change
   `stock_quantity` alone. Confirmed the stock value updated while
   `approval_status` stayed `approved` - the one mutation that
   deliberately does not pull an approved listing back into review.
8. **Reject**: a second vendor (Luxe Accessories) submitted a new
   product; rejected it from the admin panel with a required reason.
   Confirmed `approval_status` → `rejected`, the reason stored and
   included verbatim in the rejection email, and the vendor's own
   product list showing the Rejected badge with that reason inline.
   Confirmed rejecting an *already-approved* product is refused
   (status/reason left untouched) - reject is legal only from
   `pending`. Confirmed approving a *rejected* listing (admin changing
   their mind, no vendor resubmission) is accepted - approve is legal
   from `pending` or `rejected`.
9. **Cross-vendor ownership isolation**: with the second vendor's
   product id in hand, the first vendor's session got a 404 on that
   product's edit page, delete action, and both image actions - every
   vendor-portal product route is ownership-scoped, not just
   permission-scoped, so no vendor can act on another's listing by
   guessing an id.
10. **Delete**: the owning vendor soft-deleted their own (rejected)
    product - confirmed it vanished from both their own product list
    and the storefront (it was never live to begin with, but the
    check confirms `deleted_at` is honored the same way it already
    was for platform products).
11. Confirmed the vendor dashboard's new stat tiles (Live / Pending
    Review / Rejected) reflect the vendor's real product counts,
    recomputed correctly after each transition above.
12. Full regression sweep: home, shop, admin dashboard, and the full
    admin product list all still 200; an unauthenticated request to
    `/vendor/products` redirects to vendor login rather than exposing
    anything; every new/changed file passes `php -l` with zero syntax
    errors.

## 6. Bugs found while testing (and fixed before commit)

None. Testing again hit the same PHP-built-in-dev-server quirk
documented in every module since Module 10 (first POST after a fresh
cookie jar occasionally 419s, always succeeds on immediate retry) -
confirmed once more as a dev-server artifact, not re-litigated per
instance.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| A vendor's freshly-approved product doesn't show on the storefront | Its `is_active` flag is `0` - `approval_status = 'approved'` alone isn't enough, the storefront requires both | Check `SELECT is_active, approval_status FROM products WHERE id = ...`; the vendor controls `is_active` from the edit form |
| An approved listing goes dark right after the vendor "just fixed a typo" | Expected - any full edit-form save resets `approval_status` to `pending` by design | Use the product list's inline restock field for stock-only changes, which is the one update that doesn't require re-review; content changes always do |
| 404 editing/deleting a product that clearly exists | The product's `vendor_id` doesn't match the logged-in vendor's own id | Expected and intentional - `Product::findForVendor()` scopes every vendor-portal lookup to the caller's own vendor id; this is not a bug to route around |
| Approve/Reject buttons missing from a product's admin edit page | The product's `vendor_id` is `NULL` (platform-owned) | Expected - approval workflow only applies to vendor-submitted listings; platform products are created already `approved` and never enter review |
| "Only pending listings can be rejected" / "Only pending or rejected listings can be approved" | Action attempted from a status that action doesn't allow (e.g. rejecting an already-approved listing) | Reload the product's admin edit page to see its current `approval_status` before retrying |

## 8. Next module

**Module 18 — Order splitting, commission & payouts**: when an order
contains items from multiple vendors, split it into per-vendor
sub-orders for fulfillment (Module 15's "each vendor ships their own
items" decision); calculate each vendor's commission at the
category's effective rate (the same rate this module now shows on the
product form, finally put to use); and give admin a place to record
manual payouts against what each vendor is owed. Waiting for
confirmation to proceed.
