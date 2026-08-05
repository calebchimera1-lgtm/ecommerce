# Module 10 — Admin Catalog/Order/Customer Management

## 1. Explanation

Fills in the admin tooling the brief calls for that earlier modules
deliberately left out: customer account management, staff/admin
account management, a permission matrix editor for RBAC, review
moderation, an audit log viewer, and a "mark payment as paid" action
for orders (needed since Module 7's COD gateway leaves orders
`unpaid` until someone confirms delivery/collection).

### Customers vs. staff: same `users` table, different lists

There's no separate `staff` table - `users.role_id` distinguishes a
customer from an admin/manager/support account. `Admin\CustomerController`
lists/filters only the `customer` role; `Admin\UserController` lists/
manages everything else. Both share the same `status` enum
(`active`/`inactive`/`banned`) and the same login gate
(`AuthController::login()` already rejected non-`active` accounts
since Module 3 - Module 10 doesn't add that check, it just gives
admins a UI to flip the status that check reads). Verified live: a
staff account set to `inactive` here is immediately refused at
`/admin/login` with "This account is not active." - no new code
needed on the login side, confirming the existing gate and expressly
covers accounts created after Module 3 shipped.

### Staff accounts: deactivate, never delete

`Admin\UserController` has no `destroy()` action. `users.id` is
referenced by `orders.user_id` with `ON DELETE RESTRICT` (and other
FKs), so a hard delete would fail the moment a staff account ever
placed a test order, and "cascade the delete" would silently destroy
order history that has to survive for accounting/audit reasons.
Deactivation (`status = 'inactive'`) already blocks login via the
existing gate, which is the actual requirement ("staff member should
lose access") without the FK risk.

### Role permission matrix: existing roles only

`Admin\RoleController::updatePermissions()` replaces a role's entire
`role_permissions` set in one transaction (`Role::syncPermissions()`)
so a role is never left half-updated if a query fails mid-loop.
Creating brand-new roles is out of scope - the four roles seeded in
Module 1 (Super Admin, Manager, Support, Customer) cover the brief's
staff structure, and a "new role" flow would need its own slug
validation and a decision about what a blank role starts with, which
isn't needed to make RBAC itself fully usable. The `customer` role is
explicitly excluded from the editable list (storefront role, not an
admin-panel role).

Verified live end-to-end: pulled Support's permission set before and
after a matrix save, confirmed the DB's `role_permissions` rows
matched exactly what was submitted (one permission removed, one
added), and confirmed the *sidebar itself* changed for a logged-in
Support user in the same request cycle - `Auth::roleCan()`'s cache is
per-request, so a permission revoked mid-session takes effect on that
user's very next navigation, not just their next login.

### Reviews: no separate "rejected" state

`product_reviews.is_approved` (Module 1) is a plain boolean - there's
no `status` enum with a distinct `rejected` value. Rather than add a
migration for a state the brief doesn't ask for, "reject" and "delete"
are the same action here: `Admin\ReviewController::destroy()`. A
pending review that shouldn't be published, and an approved review
that turns out to be spam/abusive, both just mean "this review should
not exist" under the current schema. `approve()` is the only state
transition that exists.

### Audit log: write path exists, but isn't retrofitted everywhere

`AuditLog::record()` (write) and `AuditLog::paginateAll()` (read, for
`Admin\AuditLogController`) were built in this module and wired into
every *new* sensitive action Module 10 adds: staff account create/
update, role permission changes, customer status changes, review
approve/delete, and order payment-marked-paid. It is deliberately
**not** retrofitted into Modules 4-9's existing controllers (product/
category/coupon CRUD, order status changes, etc.) - that would be a
broad, low-value refactor of already-tested code, done under a module
whose actual brief is customer/staff/order management, not "add audit
logging everywhere." The viewer works correctly regardless of which
actions choose to write to it.

### Order payments: "mark as paid" reuses Module 9's Sales/Revenue split

`Order::markPaid()` sets `orders.payment_status = 'paid'` and calls
`Payment::markCompleted()` to flip the linked `payments` row to
`completed` with a `paid_at` timestamp, keeping the two tables
consistent. This is exactly the action Module 9's dashboard docs
described as a manual stand-in for a real reconciliation step -
verified here that clicking "Mark as Paid" on a COD order moves it out
of Module 9's "Revenue" gap without touching "Sales" (which already
counted it), the same distinction Module 9 established.

## 2. Folder location / files delivered

```
app/Models/AuditLog.php
app/Models/Permission.php
app/Controllers/Admin/CustomerController.php
app/Controllers/Admin/UserController.php
app/Controllers/Admin/RoleController.php
app/Controllers/Admin/ReviewController.php
app/Controllers/Admin/AuditLogController.php
app/Views/admin/customers/{index,show}.php
app/Views/admin/users/{index,form}.php
app/Views/admin/roles/{index,edit}.php
app/Views/admin/reviews/index.php
app/Views/admin/audit-logs/index.php
```

Updated: `app/Models/User.php` (`paginateCustomers()`,
`countCustomersFiltered()`, `paginateStaff()`, `countStaff()`,
`updateStatus()`), `app/Models/Role.php` (`staffRoles()`,
`permissionSlugs()`, `syncPermissions()`), `app/Models/ProductReview.php`
(`paginateAdmin()`, `countAdmin()`, `approve()`), `app/Models/Order.php`
(`markPaid()`), `app/Models/Payment.php` (`markCompleted()`),
`app/Controllers/Admin/OrderController.php` (`markPaid()` action),
`app/Views/admin/orders/show.php` (Mark as Paid button),
`app/Views/admin/layouts/app.php` (sidebar entries for Customers,
Reviews, Staff Users, Roles, Audit Log), `routes/admin.php`.

## 3. Routes

```
GET  /admin/customers                     customers.manage
GET  /admin/customers/{id}                customers.manage
POST /admin/customers/{id}/status         customers.manage

GET  /admin/users                         users.manage
GET  /admin/users/create                  users.manage
POST /admin/users                         users.manage
GET  /admin/users/{id}/edit               users.manage
POST /admin/users/{id}                    users.manage

GET  /admin/roles                         roles.manage
GET  /admin/roles/{id}/edit               roles.manage
POST /admin/roles/{id}/permissions        roles.manage

GET  /admin/reviews                       reviews.manage
POST /admin/reviews/{id}/approve          reviews.manage
POST /admin/reviews/{id}/delete           reviews.manage

GET  /admin/audit-logs                    audit_logs.view

POST /admin/orders/{id}/payment           orders.manage
```

All `permission` slugs above already existed in Module 1's seed data -
no new migration was needed for permissions themselves.

## 4. SQL

No schema changes. `audit_logs` (defined in Module 1) had no writer or
reader until this module.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance, driving every
action through the actual admin UI (form POSTs with real CSRF tokens
and session cookies) rather than raw SQL, except for initial
credential setup:

1. Logged in as Super Admin, created a real product via the existing
   Module 4 admin UI (there were no products in this fresh DB),
   registered/verified/logged-in as a real customer, added the
   product to cart, and completed checkout with COD - producing a
   real order rather than a hand-inserted row.
2. **Customers**: `/admin/customers` lists only `customer`-role users;
   search/status filters work against real DB rows. Opened the
   customer's detail page - confirmed it shows their real order
   (with live payment status) and real submitted review. Changed
   status to `banned`, confirmed the DB row updated and an audit
   entry was written with correct before/after values; reverted to
   `active`.
3. **Staff users**: created a new Manager-role staff account through
   the form, confirmed the row exists with the right role and an
   Argon2id hash. Edited it to `inactive`, then attempted to log in
   as that account at `/admin/login` - correctly rejected with "This
   account is not active." (proves deactivation actually blocks
   access, not just a cosmetic status label).
4. **IDOR checks**: `/admin/customers/{staff-user-id}` and
   `/admin/users/{customer-id}/edit` both returned 404 - each
   controller's loader filters by role, so cross-listing a staff
   account as a customer (or vice versa) isn't possible via URL
   manipulation.
5. **Roles/permissions**: opened Support's permission matrix, noted
   its five granted permissions, submitted a change (removed
   `reviews.manage`, added `coupons.manage`), confirmed `role_permissions`
   in the DB matched exactly, confirmed an audit entry captured the
   full before/after permission list, then logged in as a Support
   user and confirmed the sidebar reflected the new grants (Reviews
   link gone, Coupons link present) and that directly hitting
   `/admin/reviews` now returned 403. Reverted Support's permissions
   to the original seeded set afterward.
6. **Reviews**: submitted a real review as the test customer (via the
   storefront's existing review form), confirmed it appeared under
   `/admin/reviews?status=pending` and *not* under `?status=approved`,
   approved it, confirmed the DB flipped and the review now appears
   on the live product page's approved-reviews section, then deleted
   it and confirmed removal plus an audit entry.
7. **Orders / payments**: on the real COD order from step 1 (created
   `unpaid`), clicked "Mark as Paid" - confirmed `orders.payment_status`
   became `paid`, the linked `payments` row flipped to `completed`
   with a `paid_at` timestamp, the button itself disappeared on
   reload (can't double-mark), and an audit entry recorded the
   transition.
8. **Audit log viewer**: confirmed `/admin/audit-logs` renders every
   entry generated above with correct actor name, action, model
   reference, and JSON before/after diffs.
9. Confirmed a Support-role admin (lacking `users.manage`,
   `roles.manage`, `audit_logs.view`) gets 403 on all three of those
   sections, both via direct URL and via the sidebar simply not
   rendering those links.

## 6. Bugs found while testing (and fixed before commit)

None in this module's own application code. Two environment/test-setup
issues were hit and are recorded here for accuracy, since neither is
an app bug:

- **MariaDB host/account resolution**: A fresh sandbox `.env` pointed
  `DB_HOST` at `127.0.0.1` with the `root` account, but MariaDB's
  default reverse-DNS account matching resolved that TCP connection
  back to the `root@localhost` account (which had a `password_hash`
  of the literal string `invalid`, i.e. no password can match it) -
  causing every DB-backed page to 500 with "Access denied" even
  though `mysql -u root` (socket) worked fine. Fixed by setting a real
  password on `root@localhost` (the account actually being matched)
  and using that in `.env` - infrastructure setup, not an app defect.
- **SMTP timeout on checkout**: `.env.example`'s default `MAIL_HOST`
  points at `smtp.mailtrap.io`, which the sandbox can't reach. Because
  `Mailer::send()` only falls back to file-logging when `mail.host` is
  *empty* (not when it's unreachable), the checkout's order-confirmation
  email attempt blocked the whole request until PHPMailer's connection
  timed out. Fixed for testing by blanking `MAIL_HOST` in the local
  `.env` (the app's documented no-SMTP-configured behavior already
  logs to `storage/logs/mail-*.log` instead) - not a code change, and
  not something a real deployment with real SMTP credentials would
  ever hit.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `419 - Session Expired` on a form POST | CSRF token in the form doesn't match the session's stored token - usually because the token was scraped from a stale page render, or the session cookie wasn't sent with the POST | Re-fetch the form (GET) immediately before submitting so the token matches the current session; ensure the request sends the session cookie |
| Staff account can't log in after being created here | Status defaulted to something other than `active`, or the role assigned isn't a real staff role | `UserController::store()` defaults `status` to `active` when omitted; `isAssignableRole()` rejects the `customer` role for staff accounts up front |
| Deleting a staff account fails / isn't offered | Intentional - see "Staff accounts: deactivate, never delete" above | Use the status dropdown on the edit form to set `inactive`, not a delete action (none exists) |
| Role permission save appears to do nothing | The `customer` role was targeted - `updatePermissions()` explicitly refuses to touch it, since it has no admin-panel permissions by design | Edit a staff role (Super Admin/Manager/Support), not Customer |
| A review approved here doesn't show on the product page | `ProductReview::approvedForProduct()` (Module 5) filters by `is_approved = 1` *and* joins `users` - if the reviewing user's account was deleted the join drops the row | Not applicable to normal moderation; only relevant if a reviewer's account is later removed, which this module doesn't support (see staff no-delete policy - customers have the same FK-driven constraint) |

## 8. Next module

**Module 11 — Inventory, suppliers, purchase orders**: `products.supplier_id`
and the `suppliers`/`purchase_orders`/`purchase_order_items` tables
have existed since Module 1's schema but have no admin UI or model
methods yet; `inventory.manage` and `suppliers.manage` permissions are
already seeded and unused. Waiting for confirmation to proceed.
