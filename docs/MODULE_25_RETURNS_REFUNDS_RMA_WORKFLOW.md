# Module 25 — Returns/Refunds (RMA) Workflow

## 1. Explanation

Before this module, an order could be marked `refunded` (an enum value
on `orders.payment_status`) but nothing in the app ever set it except
a direct database edit - there was no actual request/approval/refund
flow behind that status. This module adds one: a customer requests a
return on specific items from a delivered order, an admin
approves/rejects it, and - separately - marks an approved request
refunded, which is the point stock gets restocked and vendor payout
figures get adjusted.

### Data model

Three new tables (`database/migrations/0008_add_return_requests.sql`),
mirroring the `order_status_history` / `vendor_order_status_history`
pattern already established for every other status trail in this
codebase:

- **`return_requests`** — one row per request. `status` is
  `pending → approved|rejected`, and `approved → refunded`. Carries
  `reason` (customer-supplied), `admin_note` (set on
  approve/reject/refund), `refunded_amount`/`refunded_at`, and
  `processed_by`.
- **`return_request_items`** — one row per order_item included in the
  request, with its own `quantity` (may be less than the original
  line's quantity) and optional per-item `reason`. `restocked` tracks
  whether that specific line was put back into sellable stock.
- **`return_request_status_history`** — an append-only trail of every
  status change, same shape as `order_status_history`.

Requests are scoped **per order_item**, not the whole order - a
customer can return one line out of a larger order, and can return the
same order_item across multiple requests as long as the combined
active (non-rejected) quantity never exceeds what was originally
ordered.

### Eligibility: whole-order gate, item-level quantity tracking

Return eligibility is gated at the **whole order's** `status`, not
per-vendor-suborder: a customer can request a return once
`orders.status = 'delivered'`, even though Module 18 already lets
different vendors within one order reach `delivered` at different
times. This is a deliberate scoping simplification. The alternative -
per-vendor eligibility - is real complexity (per-item eligibility
checks, per-item return windows) for what's the exception rather than
the rule in this catalog; gating on the order's own status keeps the
customer-facing rule simple and predictable ("my order is delivered, I
can request a return") at the cost of a customer needing the *whole*
order delivered even if they only want to return one vendor's item.

The return **window** (`settings.return_window_days`, seeded to 14) is
computed from `OrderStatusHistory::deliveredAt()` - the most recent
`created_at` where `status = 'delivered'` - not from
`orders.updated_at`. `updated_at` changes on any edit to the order
(e.g. an admin adding a shipment note), which would silently reset the
customer's return window on an unrelated change; the status-history
lookup only moves when the order actually re-enters `delivered`.

Per-item remaining quantity is computed on every request/render, never
cached: `order_items.quantity` minus
`ReturnRequestItem::activeQuantityForOrderItem()`, which sums quantity
across every **non-rejected** request for that order_item. A rejected
request's quantity doesn't count against the item - the customer is
free to request it again (verified live: rejecting a return on item 8
of order 8 immediately made its 2 units eligible again).

### Customer flow

- `GET /account/returns` — list of the customer's own requests.
- `GET /account/orders/{orderNumber}/return` — pick items/quantities
  on a delivered, in-window order; only shows lines with remaining
  eligible quantity.
- `POST /account/orders/{orderNumber}/return` — creates the request +
  its items + an initial `pending` history row.
- `GET /account/returns/{id}` — detail/status view, ownership-checked
  (404, not 403, for another customer's request - consistent with how
  order detail already behaves elsewhere in this app).

### Admin flow

- `GET /admin/returns` — filterable-by-status queue (reuses the
  `orders.view` permission).
- `GET /admin/returns/{id}` — detail page: approve/reject forms while
  `pending`; a refund form (reuses `orders.manage`) once `approved`.
- `POST /admin/returns/{id}/approve` / `.../reject` — pending-only,
  writes a history row.
- `POST /admin/returns/{id}/refund` — approved-only. The one step that
  touches stock and money; see below.

Approve/reject and refund are deliberately separate steps: "we agreed
to take this back" and "the money has actually moved" happen at
different times in a real store, and only the refund step should
touch inventory and vendor payout figures.

### The refund step: stock, vendor payouts, and order payment_status

`Admin\ReturnController::refund()` runs inside one DB transaction and
does three things:

1. **Restock** whichever return-line the admin explicitly checked
   (unchecked by default is *not* the behavior - the checkboxes
   default to checked, since restocking is the common case; an admin
   unchecks a line that's defective or otherwise shouldn't go back
   into sellable stock). Restocking calls the same
   `Product::adjustStock()` + `InventoryMovement::create()` pair every
   other stock-affecting flow in this app uses, with
   `InventoryMovement.type = 'return'` - a value the schema's ENUM has
   included since Module 1 in anticipation of exactly this.
2. **Vendor payout clawback**, for any return line tied to a vendor
   sub-order (`order_items.vendor_order_id IS NOT NULL`). This only
   adjusts `vendor_orders.subtotal` / `commission_amount` /
   `payout_amount` **downward** when that vendor_order's
   `payout_status` is still `'unpaid'`. If it's already `'paid'`, the
   row is left completely untouched and the admin sees an explicit
   warning instead ("adjust their next manual payout accordingly").
   This mirrors Module 15's manual-payout design: payouts are tracked,
   not automated, so once a payout is marked paid, that record
   represents money that has actually left the ledger - silently
   rewriting it after the fact would misrepresent what happened.
   Verified live with both cases: an already-`paid` vendor_order
   produced the warning and its figures were unchanged; a second
   return against an `unpaid` vendor_order correctly reduced subtotal,
   commission, and payout by the returned line's share (verified
   exact numbers below).
3. **Order `payment_status` sync**: flips to `'refunded'` only once
   the sum of `refunded_amount` across every refunded return request
   on that order reaches or exceeds `orders.total`. `payment_status`
   is a single enum, not a running balance, so a partial refund (the
   common case - one item out of a multi-item order) deliberately
   leaves it alone. Verified live: refunding $249 of a $969.82 order
   left `payment_status` at `unpaid`; refunding the full $215.99 of a
   $215.99 order flipped it to `refunded`.

## 2. Files

**Schema**
- `database/migrations/0008_add_return_requests.sql` — the three new
  tables + `settings.return_window_days` seed row.
- `database/kymera_collection.sql` — same tables added for fresh
  installs.
- `database/migrations/0009_drop_unused_product_returns.sql` — see
  section 5.

**Models**
- `app/Models/ReturnRequest.php`
- `app/Models/ReturnRequestItem.php`
- `app/Models/ReturnRequestStatusHistory.php`
- `app/Models/OrderStatusHistory.php` — added `deliveredAt()`.

**Controllers**
- `app/Controllers/Customer/ReturnController.php`
- `app/Controllers/Admin/ReturnController.php`

**Views**
- `app/Views/customer/account/returns/{index,create,show}.php`
- `app/Views/admin/returns/{index,show}.php`
- `app/Views/partials/account-nav.php` — added a "Returns" link.
- `app/Views/customer/account/orders/show.php` — added a "Request a
  Return" button, shown only when `order.status === 'delivered'`.
- `app/Views/admin/layouts/app.php` — added a "Returns" sidebar entry.

**Routes**
- `routes/web.php` — `/account/returns`, `/account/returns/{id}`,
  `/account/orders/{orderNumber}/return` (GET+POST).
- `routes/admin.php` — `/admin/returns`, `/admin/returns/{id}`,
  `/admin/returns/{id}/approve`, `/.../reject`, `/.../refund`.

**Tests**
- `tests/Feature/Models/ReturnRequestTest.php` — 7 tests covering
  `deliveredAt()`, active-quantity exclusion of rejected requests, the
  approve/reject/refund status transitions and their history rows,
  user-scoping (`forUser`, `belongsToUser`), and admin filtering.
- `tests/IntegrationTestCase.php` — added the three new tables to
  `TRUNCATE_TABLES`; see section 5 for why.

## 3. How to test

**Customer side**
1. Log in, have an order reach `delivered` (admin can walk it through
   `processing → shipped → delivered` from `/admin/orders/{id}`).
2. From `/account/orders/{orderNumber}`, click "Request a Return"
   (only shown on delivered orders).
3. Check an item, set a quantity (capped at what's left to return),
   give an overall reason, submit. Redirects to the new request's
   detail page at `/account/returns/{id}`.
4. `/account/returns` lists it with a status badge.

**Admin side**
1. `/admin/returns` lists all requests, filterable by status.
2. Open a `pending` one, approve or reject (rejecting requires a
   note).
3. Once `approved`, the refund form appears - amount pre-filled with
   the item line total, per-item restock checkboxes (checked by
   default). Submitting restocks checked items, adjusts unpaid vendor
   payouts, and marks the request `refunded`.

## 4. Live verification performed

All of the following was exercised against the real running app (not
just read through), following this session's established practice of
testing every module end-to-end before considering it done:

- Drove three separate orders through `pending → processing → shipped
  → delivered` via the real admin status-update endpoint, so
  `OrderStatusHistory::deliveredAt()` had real data to read.
- Submitted a return request through the actual customer form,
  confirmed the exact expected rows landed in `return_requests`,
  `return_request_items`, and `return_request_status_history`.
- Confirmed the customer detail page, the "My Returns" list, and the
  admin queue/detail pages all render the request correctly, including
  the "Returns" links in both the customer account nav and the admin
  sidebar.
- Approved a request, then refunded it with restock checked against a
  vendor sub-order whose `payout_status` was already `'paid'`:
  confirmed stock incremented (97 → 98), the `paid` vendor_order's
  figures were left byte-for-byte unchanged, and the admin saw the
  "already paid" warning.
- Repeated against an `unpaid` vendor_order (same vendor_order,
  flipped back to `unpaid` to exercise this path): confirmed the exact
  clawback math - `subtotal` 498.00 → 249.00, `commission_amount`
  74.70 → 37.35, `payout_amount` 423.30 → 211.65, all matching
  `returned line ($249) × 15% commission` by hand.
- Confirmed `orders.payment_status` stayed `unpaid` after a $249
  partial refund on a $969.82 order, then flipped to `refunded` after
  a $215.99 refund fully covered a $215.99 order.
- Confirmed a rejected request frees its quantity back up for a new
  request on the same order_item.
- Confirmed **server-side** validation blocks over-requesting beyond
  the remaining eligible quantity even when the request bypasses the
  form's HTML `max` attribute (posted `quantity_8=99` directly against
  an item with 2 remaining - no row was created).
- Confirmed access control: a nonexistent/foreign return request
  returns 404; a non-delivered order's return page redirects with an
  error; an unauthenticated request to `/admin/returns` redirects to
  admin login; a logged-in customer hitting `/admin/returns` gets 403.
- Ran the full PHPUnit suite (67 tests, up from 60) three times in a
  row with identical results, and `phpcs` against every new/changed
  PHP file (0 errors, 0 warnings).

## 5. Bugs found while testing (and fixed before commit)

**A dormant, unused competing schema.** While setting up test data,
`SHOW TABLES` turned up `product_returns` - a table that has existed
since the very first Module 1 schema but was never referenced by any
application code (`grep -rn "product_returns\|ProductReturn" app/`
returned nothing outside the schema file itself). It was clearly
scaffolding for a returns feature that never got built, the same way
`inventory_movements.type` had a `'return'` ENUM value sitting unused
since Module 1 until this module finally used it. Having two
competing "returns" table sets - the old dormant `product_returns` and
the new, actually-wired `return_requests`/`return_request_items` -
would confuse any future reader trying to understand how returns
work. Removed `product_returns` from the base schema entirely and
added `database/migrations/0009_drop_unused_product_returns.sql` to
drop it from any database that was seeded before this change.

**Fixture-table truncation gap in `IntegrationTestCase`.** The three
new tables weren't in `TRUNCATE_TABLES`, and `return_requests.order_id`
has `ON DELETE CASCADE` to `orders` - but `TRUNCATE` doesn't cascade,
and the truncation loop runs with `FOREIGN_KEY_CHECKS = 0` specifically
so it doesn't have to. That combination meant a test creating a return
request, followed by a *different* test that truncates `orders`
(resetting its auto-increment), would leave the orphaned
`return_requests` row pointing at an order id that a later test's new
order could reuse - silently joining one test's leftover return
request onto a different test's unrelated order data. Caught by
inspecting the truncation list against the new schema's foreign keys
before writing the test suite, not by observing a flaky failure. Fixed
by adding `return_request_status_history`, `return_request_items`, and
`return_requests` to `TRUNCATE_TABLES`.

**Disabled-input array-misalignment in the customer return form** (caught
during initial build, before any testing - see the commit history for
Module 25 in this session). The first draft paired a checkbox array
(`order_item_id[]`) with a same-shaped quantity array (`quantity[]`)
positionally, using a JS `onchange` handler to `disable` the quantity
input for unchecked rows. Browsers never submit `disabled` fields, so
unchecking a middle row would misalign every quantity after it against
the wrong order_item_id. Rewritten to use checkbox `value` attributes
(`selected_item_id[]`) combined with uniquely-named per-item fields
(`quantity_{id}`, `item_reason_{id}`) - no positional pairing, no
`disabled` attribute, immune to the class of bug entirely rather than
patched around it.

## 6. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| "Returns can only be requested for delivered orders" on an order you can see is delivered in the admin panel | The order's *overall* `status` isn't `delivered` even though one vendor's sub-order is - eligibility is gated at the whole-order level by design (section 1) | Wait for every vendor's sub-order (and the parent order) to reach `delivered`, or - if per-vendor eligibility is genuinely needed later - that's a real scope change, not a bug |
| "You requested more units than are available to return" even though the form's quantity field only went up to N | A concurrent return request (or a second browser tab) already claimed some of the remaining quantity between page load and submit | Reload the return form - `returnable_quantity` is always computed fresh from the current `return_request_items` rows, never cached |
| Refunding a return didn't reduce a vendor's payout figures | That vendor_order's `payout_status` was already `'paid'` - the admin should have seen a warning message saying so | Adjust that vendor's *next* manual payout to account for the return; this is intentional (section 1), not a bug |

## 7. Next module

**Module 26 — Abandoned cart recovery emails**: unlike every prior
module, this one isn't purely request-driven - detecting an
"abandoned" cart requires a background/scheduled check (a cart with
items, last touched N hours ago, no matching order since), which this
codebase doesn't have a mechanism for yet. Needs a cron-style trigger
point (a CLI entry script callable from the system crontab, following
`DEPLOYMENT.md`'s existing "server requirements" section) rather than
a new route.
