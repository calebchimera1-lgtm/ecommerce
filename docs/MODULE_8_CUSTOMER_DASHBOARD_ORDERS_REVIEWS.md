# Module 8 — Customer Dashboard, Order History, Tracking, Reviews

## 1. Explanation

Replaces Module 2's placeholder `/account` with a real customer area:
a dashboard, order history, order tracking (first real use of the
`shipments` table), a "My Reviews" management page, and a full
profile page (info edit, password change, address book - first real
use of `user_addresses`). Also adds a minimal admin order controller,
because the tracking feature has nothing to display without a way for
staff to actually move an order through statuses and attach shipment
info.

### Dashboard, order history, and tracking

- **`/account`** — stat tiles (order count, wishlist count, review
  count) and the 5 most recent orders.
- **`/account/orders`** — full order history, linking to both a
  tracking view and the printable invoice from Module 7.
- **`/account/orders/{orderNumber}`** — the actual "Track Orders"
  feature: a progress bar across `pending → processing → shipped →
  delivered` (or a plain notice for `cancelled`/`refunded`), the
  shipment's courier/tracking number/status/estimated delivery if one
  exists, and the full `order_status_history` timeline. Ownership-
  checked the same way Module 7's confirmation/invoice pages are.

### My Reviews

`ProductReview::forUser()` lists everything a customer has written,
with its approval state visible. Customers can edit or delete their
own review (`ProductReview::belongsToUser()` guards both, verified
against a second account getting a 404, not just a blocked action).
**Editing resets `is_approved` to 0** — otherwise a customer could get
a review approved, then edit it to say something else entirely without
re-moderation, silently bypassing the whole point of approval.

### Profile: info, password, and the address book

- **Info + password** are ordinary update forms, with the same
  password-hashing and current-password-verification pattern
  established in Module 2.
- **Changing email re-triggers verification**, reusing Module 2's
  exact mechanism: `email_verified_at` is cleared, a new hashed
  verification token is stored, and the same `verify-email` template
  is sent to the *new* address. This matches the security posture the
  app has held since Module 2 rather than treating email as a plain
  editable field.
- **Address book** (`user_addresses`, defined in Module 1, unused
  until now): add/edit/delete, with an exclusive "default" flag -
  setting one address default clears the flag on every other address
  for that user first, verified directly (adding a second default
  address un-defaults the first).

### The minimal admin Order controller

Order tracking is meaningless to test - or use - without a way for
staff to actually update an order's status and shipment info. Rather
than defer that entirely to a later "admin order management" module
and ship an untestable tracking feature, this module includes just
enough: a list (`orders.view`), a detail view with status-update and
shipment forms (`orders.manage`), gated exactly like every other admin
resource. Fuller admin order tooling (refunds, bulk actions, exports)
remains out of scope here - this is deliberately narrow, the same
justification Module 6 used for its minimal Coupon CRUD.

## 2. Folder location / files delivered

```
app/Models/Shipment.php
app/Models/UserAddress.php
app/Controllers/Customer/ReviewController.php
app/Controllers/Customer/ProfileController.php
app/Controllers/Customer/AddressController.php
app/Controllers/Admin/OrderController.php
app/Views/customer/account/orders/index.php
app/Views/customer/account/orders/show.php
app/Views/customer/account/reviews/index.php
app/Views/customer/account/reviews/edit.php
app/Views/customer/account/profile/index.php
app/Views/customer/account/profile/_address_form.php
app/Views/admin/orders/index.php
app/Views/admin/orders/show.php
app/Views/partials/account-nav.php
```

Updated: `app/Controllers/Customer/DashboardController.php` (real
data, replacing the Module 2 placeholder),
`app/Controllers/Customer/OrderController.php` (added `history()`/
`show()` alongside Module 7's `confirmation()`/`invoice()`),
`app/Models/Order.php` (`paginateAll()`, `countAll()`,
`updateStatus()`), `app/Models/OrderStatusHistory.php` (`forOrder()`),
`app/Models/ProductReview.php` (`forUser()`, `belongsToUser()`),
`app/Models/Wishlist.php` (`countForUser()`),
`app/Views/customer/account/dashboard.php` (full rewrite),
`app/Views/admin/layouts/app.php` (Orders sidebar link), `routes/web.php`,
`routes/admin.php`.

## 3. Routes

| Method | Path | Notes |
|---|---|---|
| GET | `/account`, `/account/orders`, `/account/orders/{orderNumber}` | Auth required |
| GET/POST | `/account/reviews`, `/account/reviews/{id}/edit`, `/account/reviews/{id}`, `/account/reviews/{id}/delete` | Auth + ownership |
| GET/POST | `/account/profile`, `/account/password` | Auth required |
| POST | `/account/addresses`, `/account/addresses/{id}`, `/account/addresses/{id}/delete` | Auth + ownership |
| GET | `/admin/orders`, `/admin/orders/{id}` | `orders.view` |
| POST | `/admin/orders/{id}/status`, `/admin/orders/{id}/shipment` | `orders.manage` |

## 4. SQL

No schema changes - the first real use of `shipments` and
`user_addresses` (both defined in Module 1), plus `order_status_history`
now being *read*, not just written (Module 7 only wrote the initial
"pending" entry).

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance, using a full
purchase (via the actual admin panel and checkout flow, not seeded
directly) as the basis for every check:

1. Place an order as a customer (Module 7's flow). Confirm `/account`
   shows it in Recent Orders with correct stat tiles, and
   `/account/orders` lists it.
2. Visit `/account/orders/{orderNumber}` - confirm the progress bar
   and status default to "Pending."
3. As Super Admin, open `/admin/orders`, view the order, set status to
   "Processing" with a note, then attach shipment info (courier,
   tracking number, "In Transit," an estimated delivery date), then
   advance status to "Shipped."
4. Revisit the customer tracking page - confirm the progress bar now
   highlights Pending/Processing/Shipped, the shipment card shows the
   courier/tracking/ETA exactly as entered, and the status history
   lists all three transitions with their notes and timestamps.
5. **Reviews**: submit a review, confirm it's `is_approved = 0` and
   shows "Pending approval" on `/account/reviews`. Edit it - still
   pending. Approve it directly in the DB, edit again - confirm it
   goes back to `is_approved = 0` (moderation reset). Confirm a
   *different* logged-in account gets 404 trying to edit or delete it.
6. **Profile**: update name/phone (no email change) - takes effect
   immediately. Change the password - old password then fails login,
   new one succeeds. Change the email - `email_verified_at` clears and
   a new verification email is logged; existing session isn't force-
   logged-out (verification is checked at the next login, matching
   Module 2's existing design, not a new gap).
7. **Address book**: add an address, add a second marked default -
   confirm the first address's `is_default` flips to 0. Update an
   address's details. Confirm a different account can't delete your
   address (silent no-op, address survives).

## 6. Bugs found while testing (and fixed before commit)

| Bug | File | Symptom | Fix |
|---|---|---|---|
| `account-nav.php` partial created in the wrong directory | Originally written to `app/Views/customer/partials/account-nav.php`, but every page's `require` path (matching the established convention from `alerts.php`/`pagination.php`/`product-card.php`, all under the shared `app/Views/partials/`) pointed at `app/Views/partials/account-nav.php` | Dashboard, order history, and order tracking pages all threw a `require()` warning and silently rendered without the account nav (and, because the warning fired after output had started, a second `headers already sent` warning on every affected page). Moved the file to the correct shared `partials/` directory to match where every `require` call actually looks, rather than editing five call sites to match the wrong location |

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Order tracking page shows no shipment card | No shipment record exists yet for that order | Expected until an admin adds one via `/admin/orders/{id}` - the page correctly shows just the status progress bar until then |
| A customer's review edit "disappears" (goes to pending) after being previously visible on the product page | By design - editing always resets `is_approved` to 0 | Not a bug; re-approve via `/admin/orders`... actually via the DB directly for now, since review-moderation admin UI is a later module (this one only needed enough to demonstrate and test the reset behavior) |
| Changing email doesn't seem to "do" anything visible immediately | The UI still shows the old email until the new one is verified and you reload with fresh session data, or - simpler - just check `users.email` and `email_verified_at` directly | Expected; the flash message explains a verification email was sent |

## 8. Next module

**Module 9 — Admin dashboard & analytics**: real widgets (today's/
monthly sales, revenue, low-stock alerts, latest orders, recent
customers) replacing Module 3's placeholder admin dashboard, backed by
the real order/product data every module since Module 4 has been
generating. Waiting for confirmation to proceed.
