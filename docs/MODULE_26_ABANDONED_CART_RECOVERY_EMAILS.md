# Module 26 — Abandoned Cart Recovery Emails

## 1. Explanation

Every prior module in this build (1 through 25) was request-driven -
some HTTP request comes in, a controller handles it, done. Abandoned
cart detection is the first genuinely time-based feature: "this
customer's cart has sat untouched for N hours" isn't something any
incoming request can tell you: it depends on the *absence* of a
request. That needs something to periodically check, which this app's
architecture doesn't have a mechanism for - no queue worker, no
background daemon, no in-app scheduler. The fix is the standard one
for a codebase like this: a small CLI script invoked by the system
crontab.

### Scope decision: logged-in customers only

`carts.user_id` is nullable - a guest browsing before logging in gets
a cart identified by `session_id` instead. A guest cart genuinely
can't receive a recovery email: there's no address to send it to, and
this app doesn't collect one before checkout (no guest checkout - see
the `routes/web.php` comment on `/checkout`). `Cart::abandoned()`
therefore only ever considers carts where `user_id IS NOT NULL`.

### Detecting "abandoned" without a queue or event system

A cart is abandoned when: it belongs to a logged-in user, it still has
at least one item, and it's sat untouched for at least
`settings.abandoned_cart_threshold_hours` (seeded to 24). "Still has
items" is doing double duty here for free - `OrderPlacementService`
already clears `cart_items` on successful checkout (see Module 18), so
a cart that became an order simply has no items left and is never a
candidate; no separate "was this converted?" check is needed.

**A real bug caught while testing, before this ever reached
production**: the first version of `Cart::abandoned()` compared
against `carts.updated_at` alone. That's wrong. Adding an item to a
cart runs entirely against the separate `cart_items` table
(`CartItem::addOrIncrement()`) and never touches the parent `carts`
row - so `carts.updated_at` only moves when something cart-level
changes (a coupon, a shipping method), not when the customer actually
adds or removes items, which is the overwhelmingly common case. Live
testing caught this directly: adding an item to a cart whose row had
been backdated past the threshold did *not* change `carts.updated_at`
at all, meaning the real detection query would have called an
actively-being-shopped-in cart "abandoned." Fixed by computing last
activity as `GREATEST(carts.updated_at, MAX(cart_items.updated_at))`
across a join, not a bare column read.

### Avoiding duplicate emails without a full send-log table

`carts.reminder_sent_at` (one nullable timestamp column, not a
separate log table - there's nothing here that needs a history, just
"was the *current* idle spell already handled") is compared against
that same last-activity timestamp: eligible when `reminder_sent_at IS
NULL OR reminder_sent_at < last_activity_at`. This means:

- A cart is only ever emailed once per idle spell - re-running the
  cron script every hour (the suggested schedule) never double-sends,
  since the second run's `reminder_sent_at` is no longer `NULL` and
  isn't older than an unchanged `last_activity_at`.
- If the customer comes back, adds another item, and goes idle again,
  `last_activity_at` moves forward past the old `reminder_sent_at`,
  making the cart eligible for a fresh reminder once it's idle for the
  threshold again. Verified live (see section 4) - simulated a
  reminder sent 48h ago, a touch 30h ago, and confirmed the cart was
  correctly picked up again once 24h had passed since that touch.

### The script itself

`bin/send-abandoned-cart-emails.php` is a standalone entry point (not
routed through `public/index.php` - cron invokes it directly), guarded
to only run under the CLI SAPI. It doesn't need `App::run()`'s full
bootstrap (session start, security headers, routing) - just
`vendor/autoload.php`, which is enough to reach `config()`,
`Database::connection()`, and every model, exactly like any other PHP
entry point in this codebase. For each eligible cart it renders
`app/Views/emails/abandoned-cart.php` (the item list + a link back to
`/cart`) through the existing `Mailer::send()` from Module 2/7, which
already handles the "no SMTP configured → log to
`storage/logs/mail-*.log` instead" fallback this session's earlier
modules established - nothing new needed there. On success it calls
`Cart::markReminderSent()`; on a `Mailer::send()` failure it's counted
but left unmarked, so a transient SMTP failure doesn't permanently
skip that cart - the next hourly run retries it.

## 2. Files

- `database/migrations/0010_add_abandoned_cart_recovery.sql` —
  `carts.reminder_sent_at` column + `settings.abandoned_cart_threshold_hours` seed row.
- `database/kymera_collection.sql` — same, for fresh installs.
- `app/Models/Cart.php` — added `abandoned()` and `markReminderSent()`.
- `app/Views/emails/abandoned-cart.php` — the recovery email template.
- `bin/send-abandoned-cart-emails.php` — the cron entry point.
- `composer.json` — `lint` script now also covers `bin/`.
- `DEPLOYMENT.md` — new "Scheduled jobs (cron)" section with the
  crontab line to add, and an updated post-deploy checklist item.
- `tests/Feature/Models/CartTest.php` — 6 tests covering the
  threshold, the empty-cart and guest-cart exclusions, and both
  reminder-dedup directions.

## 3. How to test

```bash
php bin/send-abandoned-cart-emails.php
```

Locally (no `MAIL_HOST` configured), sent reminders land in
`storage/logs/mail-{date}.log`; a summary line
(`Abandoned cart reminders: N eligible, M sent, F failed (threshold
Hh)`) is both echoed and written to `storage/logs/{date}.log`. To
manufacture an eligible cart for testing, add an item to a cart
through the real UI, then backdate both rows past the threshold (note
`ON UPDATE CURRENT_TIMESTAMP` means you must include `updated_at` in
the same `UPDATE` statement, or a *later* unrelated update to the row
will silently reset it back to now):

```sql
UPDATE cart_items SET updated_at = DATE_SUB(NOW(), INTERVAL 30 HOUR) WHERE cart_id = :id;
UPDATE carts SET updated_at = DATE_SUB(NOW(), INTERVAL 30 HOUR) WHERE id = :id;
```

## 4. Live verification performed

- Added a real item to a logged-in customer's cart through the actual
  `/cart/add` endpoint, backdated both `carts.updated_at` and
  `cart_items.updated_at` past the 24h default threshold, ran the
  script, and confirmed: exactly 1 eligible/1 sent, the rendered email
  in `storage/logs/mail-*.log` contained the correct product name,
  quantity, and price, and `carts.reminder_sent_at` was set.
- Re-ran the script immediately after with no changes: 0 eligible,
  confirming no duplicate send.
- Caught and fixed the `carts.updated_at`-only detection bug described
  in section 1 by observing, live, that adding an item to an already-
  backdated cart did not change `carts.updated_at` and so did not
  correctly exclude an actively-shopped-in cart from a manual re-check
  - not caught by reading the code, only by exercising it.
- Added a second item through the real UI to an already-backdated
  cart and confirmed the cron script correctly found 0 eligible carts
  immediately after (freshly touched, not idle), then re-backdated
  both tables and confirmed it became eligible again.
- Simulated the full "reminded once, touched again later, idle again"
  cycle with explicit timestamps (reminder 48h ago, touch 30h ago,
  24h threshold) and confirmed the cart was correctly re-included.
- Confirmed a cart with no items (converted to an order, or simply
  never added to) is never included even when heavily backdated, and
  that a guest cart (`user_id IS NULL`) is never included regardless
  of age.
- Ran `composer lint` (now including `bin/`), `composer cs` (0
  errors/warnings), and the full PHPUnit suite three times in a row
  (73 tests, up from 67, identical results each run).

## 5. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| The cron job runs but no emails ever go out, even for a cart you know is old | `carts.updated_at` looked recent because a cart-level field (coupon, shipping method) was touched after the actual last item change, or - more likely if you're testing manually - you updated `reminder_sent_at`/another column on the `carts` row without also re-specifying `updated_at`, which silently reset it to now via `ON UPDATE CURRENT_TIMESTAMP` | Check `GREATEST(carts.updated_at, MAX(cart_items.updated_at))` directly in SQL for the cart in question; when manually backdating for a test, always include `updated_at` explicitly in every `UPDATE` you run against that row afterward |
| A customer says they never got a recovery email despite an obviously idle cart | `MAIL_HOST` isn't set, so delivery is going to `storage/logs/mail-*.log` instead of a real inbox (Module 2's established local-dev fallback) - not a bug in this module | Configure real SMTP credentials per `DEPLOYMENT.md` section 8, then re-verify with a real send |
| Cron never fires at all | Nothing invokes `bin/send-abandoned-cart-emails.php` - this app has no built-in scheduler, cron has to be configured by whoever deploys it | Add the crontab entry from `DEPLOYMENT.md` section 10 |

## 6. Next module

**Module 27 — Multi-currency / regional tax rules**: the current
`TaxRate` model is a flat single-rate table with no notion of a
customer's region or a product's currency - both checkout and every
price display assume USD and one tax rate. Real scope: a currency
selector (storage + display, not necessarily live FX conversion) and
region-aware tax rate lookup at checkout, without disturbing the
`orders`/`order_items` money columns' existing precision guarantees.
