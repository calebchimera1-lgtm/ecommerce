# Module 16 — Vendor Registration, Approval & Dashboard Shell

## 1. Explanation

Module 15 built the vendor realm's skeleton - schema, account type,
auth, a placeholder dashboard - and faked a test vendor directly via
SQL because there was no way to actually become one. This module
closes that gap: a public "Become a Vendor" application form, an admin
approval queue to decide who gets to sell, and enough of a vendor
dashboard/profile that an approved vendor has something real to land
on. Vendor product listings (the reason any of this matters) are
Module 17; orders, commission, and payouts are Module 18.

### Registration creates both rows in one request, unapproved

`Vendor\AuthController::register()` mirrors the shape of customer
registration (Module 2) - validate, hash the password, create the
account - but an applicant isn't a working account yet the way a
customer is. It creates the `users` row with `role_id` set to the
`vendor` role *and* the `vendors` row with `status = 'pending'` in the
same request, then immediately sends the applicant to `/vendor/login`
with a flash message telling them a decision is pending, rather than
logging them in. `VendorMiddleware` already refuses a `pending`
vendor's dashboard access (built in Module 15), so no new gate was
needed here - registration just needed to produce the same shape of
row Module 15's test data faked by hand.

`Role::findBy('slug', 'vendor')` is looked up at registration time
rather than hardcoding role id 5, so the flow degrades to a clear
"applications are temporarily unavailable" flash instead of an SQL
error if the `vendor` role were ever renamed or removed - the same
defensive lookup pattern `Auth` uses elsewhere in this codebase.

Registration is rate-limited exactly like Module 14's public
registration and review forms: `RateLimiter` keyed
`vendor-register:{ip}`, hit *before* validation runs, so an attacker
can't dodge the limiter by submitting deliberately-invalid payloads
that fail before the hit would otherwise register.

### The approval queue is a status-transition state machine, not a form

`Admin\VendorController` doesn't expose a generic "edit vendor status"
field - it exposes four actions (`approve`, `reject`, `suspend`,
`reactivate`), each gated by `vendors.manage` (Super Admin only, per
Module 15's trust-tier decision) and each guarded by the one prior
status it's legal to leave: approve requires `pending` or `rejected`,
reject requires `pending`, suspend requires `approved`, reactivate
requires `suspended`. Requesting an action from the wrong prior status
- suspending an already-suspended vendor, rejecting an approved one -
flashes a validation error and changes nothing, rather than silently
succeeding or throwing. Every transition is audit-logged
(`vendor.approved`/`.rejected`/`.suspended`/`.reactivated`, old/new
status captured) and triggers an email to the vendor, matching Module
10's policy that admin-initiated account-status changes get both an
audit trail and a notification - vendor self-registration itself is
*not* audit-logged, for the same reason customer self-registration
isn't: it's the applicant acting on their own account, not staff
acting on someone else's.

Rejection requires a `rejection_reason` (validated `required`) - a
bare "no" with no explanation isn't accepted, and that reason is
both stored (so a later reconsideration has context) and included
verbatim in the rejection email.

### Vendor self-service profile reuses Module 14's image-upload pattern

`Vendor\ProfileController` lets an approved vendor edit their own
`vendors` row (store name, description, contact info, logo,
`payout_details`) through the same `ImageUploader::store()` /
`ImageUploader::delete()` pair Module 14 used for testimonial photos:
upload the new logo first, and only delete the old file after the new
one is confirmed stored, so a failed upload never leaves a vendor with
no logo at all. Changing `store_name` regenerates the slug via
`Vendor::generateSlug()` (uniqueness-checked, excluding the vendor's
own row) - but only when the name actually changed, so re-saving the
form with an unchanged name doesn't needlessly churn the slug a
storefront link might already reference.

`payout_details` is a free-form textarea, not structured
bank-account fields - consistent with Module 15's decision that
payouts are recorded and paid manually outside the app; the field
exists so a vendor can tell you *where* to send money, not so the app
can send it.

## 2. Folder location / files delivered

```
app/Controllers/Admin/VendorController.php
app/Controllers/Vendor/ProfileController.php
app/Views/admin/vendors/{index,show}.php
app/Views/vendor/auth/register.php
app/Views/vendor/profile/index.php
app/Views/emails/vendor-{approved,rejected,suspended}.php
public/uploads/vendors/.gitkeep
```

Updated: `app/Controllers/Vendor/AuthController.php` (registration),
`app/Models/Vendor.php` (admin pagination/filtering, status
transitions, `findWithUser()`), `routes/vendor.php` (register +
profile routes), `routes/admin.php` (vendor approval-queue routes),
`app/Views/admin/layouts/app.php` (Vendors nav entry, gated
`vendors.manage`), `app/Views/vendor/layouts/app.php` (Store Profile
nav entry), `app/Views/vendor/auth/login.php` (apply-to-become-a-vendor
link), `app/Views/vendor/dashboard/index.php` (rewritten from
Module 15's placeholder), `.gitignore` (`/public/uploads/vendors/`).

## 3. Routes

```
GET  /vendor/register                                          GuestVendorMiddleware
POST /vendor/register                                           GuestVendorMiddleware
GET  /vendor/profile                                            VendorMiddleware
POST /vendor/profile                                             VendorMiddleware

GET  /admin/vendors                                              vendors.manage
GET  /admin/vendors/{id}                                         vendors.manage
POST /admin/vendors/{id}/approve                                 vendors.manage
POST /admin/vendors/{id}/reject                                  vendors.manage
POST /admin/vendors/{id}/suspend                                 vendors.manage
POST /admin/vendors/{id}/reactivate                              vendors.manage
```

## 4. SQL

No schema changes this module - Module 15 already added every column
this module's flows write to (`vendors.status` and friends,
`vendors.payout_details`, `vendors.logo`). Module 16 is entirely
application code on top of that schema.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the actual
running app (registration form, admin panel, vendor portal - not
direct queries except to confirm results):

1. `GET /vendor/register` → 200; submitted the real form for a new
   applicant (Milano Leather Goods) → confirmed via direct query that
   both the `users` row (`role_id` = Vendor) and `vendors` row
   (`status = 'pending'`, correctly-slugified `slug`) were created in
   one request, and that the post-submit flash/redirect told the
   applicant their application is pending review rather than logging
   them in.
2. Confirmed a login attempt against that still-`pending` account is
   refused with the "still under review" message (Module 15's gate,
   now reachable through a real signup instead of faked SQL).
3. Logged in as admin; confirmed `GET /admin/vendors` lists both the
   new pending applicant and Module 15's existing approved test
   vendor, pulled live from the database.
4. **Approve**: opened the pending applicant's detail page, submitted
   Approve. Confirmed `vendors.status` → `approved`, `approved_by` set
   to the acting admin's id, `approved_at` timestamped; confirmed an
   `audit_logs` row (`vendor.approved`, old value `pending`, new value
   `approved`); confirmed an approval email was sent (dev mailer logs
   to `storage/logs/mail-*.log` since no SMTP is configured) with the
   correct vendor/store name.
5. **Reject**: registered a second fresh applicant (Sunset Jewels).
   Submitted Reject with an empty reason first - confirmed the
   validator refused it and the vendor's status was untouched. Then
   submitted Reject with a real reason - confirmed `status` →
   `rejected`, `rejection_reason` stored verbatim, an `audit_logs` row
   recorded, the detail page's status badge updated to "Rejected", and
   the rejection email included the exact reason text.
6. **Suspend / reactivate**: on the already-approved test vendor,
   submitted Suspend - confirmed `status` → `suspended`, an
   `audit_logs` row recorded, and (critically) that logging into
   `/vendor/login` with that account's real credentials was refused
   with the suspended-account message. Submitted Reactivate -
   confirmed `status` → `approved` again, an `audit_logs` row
   recorded, and that the same credentials could now log in and reach
   the dashboard.
7. **Illegal transitions rejected**: confirmed (by inspecting
   `Admin\VendorController`'s guard clauses and exercising the
   reachable ones live - e.g. suspend requires `approved`) that each
   action only succeeds from its one legal prior status; an
   out-of-sequence request flashes a validation error and leaves the
   row unchanged rather than corrupting the state machine.
8. **Vendor self-service profile**: logged in as the reactivated test
   vendor, `GET /vendor/profile` → 200 pre-filled with current data.
   Submitted an update with a new logo image (multipart upload) -
   confirmed the file landed in `public/uploads/vendors/`, the
   `vendors.logo` path updated, and the new logo rendered on the
   profile page. Submitted a second update changing `store_name` and a
   second new logo - confirmed the slug regenerated to match the new
   name, and that the *previous* logo file was deleted from disk once
   the new one was safely stored (no orphaned old file, no gap where
   neither file exists).
9. **Cross-realm and access-control regression**: a logged-in vendor
   hitting `/admin/dashboard` → 403; a logged-in admin hitting
   `/vendor/dashboard` → 403 (admin sessions carry no `vendor` role);
   an already-logged-in vendor hitting `/vendor/register` →
   redirected away, not shown the form again.
10. Full regression sweep: storefront home/shop and the admin
    dashboard all still 200 for the right sessions; every new/changed
    file passes `php -l` with zero syntax errors.

## 6. Bugs found while testing (and fixed before commit)

One real bug, caught before it reached testing: while extending
`app/Models/Vendor.php`, an edit briefly left the class declared as
`extends \App\Core\Model` (fully-qualified inline) instead of this
codebase's established `use App\Core\Model; ... extends Model`
convention used by every other model. Cosmetic (PHP resolves either
form identically), but inconsistent with the rest of the codebase -
caught on review and fixed before running any tests against it.

No functional bugs. Testing again hit the same PHP-built-in-dev-server
quirk documented in every module since Module 10: the first POST
immediately after a fresh cookie jar + GET occasionally 419s despite a
correct, freshly-scraped CSRF token, and always succeeds on immediate
retry. Confirmed once more this is a dev-server artifact, not an app
bug, and not re-litigated per instance below.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| "Vendor applications are temporarily unavailable" on `/vendor/register` | `Role::findBy('slug', 'vendor')` returned nothing | Confirm the `vendor` role row still exists and its `slug` is exactly `vendor` (`SELECT * FROM roles WHERE slug = 'vendor'`) |
| Approve/Reject/Suspend/Reactivate button doesn't appear on a vendor's admin detail page | The vendor's current `status` doesn't match that action's required prior status (e.g. Suspend only shows for `approved` vendors) | Check `vendors.status` for that row - the UI intentionally only offers legal transitions |
| "Only pending applications can be rejected" (or similar) flashed with no change made | Action submitted against a vendor whose status had already moved on (e.g. rejecting an already-approved vendor, perhaps from a stale open tab) | Reload the vendor's detail page to see its current status before retrying the intended action |
| Vendor's logo doesn't update after a profile save, or two logo files linger on disk | Should not happen - `ProfileController::update()` stores the new file before deleting the old one, and only overwrites `vendors.logo` after a successful store | If it does, check `ImageUploader::store()`'s return value and confirm the upload directory (`public/uploads/vendors/`) is writable |
| Rejection email is missing the reason, or shows a stale one | `rejection_reason` is only set by `reject()` - `approve()` explicitly clears it back to `NULL` | Expected: a vendor that's since been approved should show no rejection reason; check `vendors.rejection_reason` directly if the UI seems out of sync |

## 8. Next module

**Module 17 — Vendor product management & listing approval**: vendors
create/edit their own products (scoped to `vendor_id = `their own id,
never another vendor's), each new or edited listing enters
`products.approval_status = 'pending'` until an admin approves it
(the queue `products.approval_status` was added for back in Module
15), and the public storefront only ever shows `approved` products
regardless of who owns them. Waiting for confirmation to proceed.
