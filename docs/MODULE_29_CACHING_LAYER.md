# Module 29 — Caching Layer for Storefront/Dashboard Queries

## 1. Explanation

By this point in the session, several of the app's highest-traffic
pages had accumulated real query cost with zero caching: the category
mega-menu re-queries `categories` on literally every page load, the
homepage runs four separate joined/subqueried product queries on every
visit, and the admin dashboard (this module's own audit found 17
separate aggregate queries in `Admin\DashboardController::index()`)
recomputes everything from scratch on every dashboard visit - which,
being the default admin landing page, is often. None of this was
urgent at the current seed-data scale, but it's exactly the kind of
cost that compounds as the catalog and order history grow, and it was
explicitly flagged as a gap when this module was scoped.

### A file-based cache, on purpose - not Redis/Memcached

`App\Core\Cache` (`app/Core/Cache.php`) is a small key-value cache
backed by plain files in `storage/cache/*.cache` (one file per key,
holding a PHP-serialized `[expires_at, value]` pair). This app's
server requirements have stayed deliberately minimal through every
module - `DEPLOYMENT.md` section 1 has said "PHP + MySQL + a web
server, nothing else" since Module 23, and `storage/cache/` has
existed (empty, with a `.gitkeep`) since the very first schema/folder
structure in Module 1, clearly anticipating exactly this. Adding a
Redis or Memcached dependency for a caching layer this app doesn't
strictly need yet would be the wrong tradeoff - it turns "clone,
`composer install`, point a web server at `public/`" into "...and
also stand up a cache server," for a single-process app that doesn't
have the request volume to need one. `Cache::remember()` is the whole
API surface most callers need:

```php
Cache::remember('some.key', $ttlSeconds, fn () => /* expensive read */);
```

### What got cached, and the invalidation story for each

**`Category::activeOrdered()`** (1 hour TTL) - read on nearly every
single page request via the site layout's mega-menu, plus the
homepage. Categories change rarely (an admin CRUD action), so this
gets *both* a long TTL as a safety net *and* explicit invalidation:
`Category::invalidateCache()` is called from every mutating action in
`Admin\CategoryController` (`store()`, `update()`, `destroy()`), so an
admin's edit is visible on the live site immediately, not after up to
an hour of staleness. Verified live (section 4) - renaming and
toggling a category's `is_active` flag both showed up on the homepage
on the very next request.

**Homepage product rails** (`newArrivals`/`trending`/`onSale`/`featured`,
5 minute TTL, `Customer\HomeController`) - no explicit invalidation
hook. A stock change, a new product listing, or a price edit could
affect any of these four lists, and chasing every one of those call
sites just to keep a homepage teaser card perfectly fresh isn't worth
it: the actual authoritative price and stock are always re-checked
live at the product detail page and again at add-to-cart/checkout, so
a homepage card being up to 5 minutes stale is a fully acceptable
tradeoff, not a correctness bug.

**Admin dashboard metrics bundle** (5 minute TTL,
`Admin\DashboardController`) - all 17 aggregate queries cached as one
bundle under a single date-suffixed key (`admin.dashboard.{today}`),
not 17 separate cache entries. There's no single admin action that
could invalidate all of them anyway (a new order, a payment, a stock
adjustment, and a new customer signup each touch a different subset of
these numbers) - a short time-based TTL is the pragmatic choice for a
summary dashboard, the same category as the homepage rails above.
Nothing on this dashboard is a figure an admin acts on with
sub-minute urgency.

### What deliberately stays uncached

Nothing about stock levels, cart contents, order status, payment
status, or vendor payout figures changed in this module. Those all
still hit the database on every single read, exactly as before - the
`Cache` class's own docblock states this explicitly as a rule, not
just a note, precisely so a future module doesn't casually wrap one of
those reads in `Cache::remember()` without re-deriving why that would
be dangerous. Module 25's RMA refund math, Module 15's payout
accounting, and the cart/checkout pipeline all depend on reading the
database's actual current state on every request; none of that was
touched here.

## 2. Files

- `app/Core/Cache.php` — new: `remember()`, `put()`, `forget()`,
  `flush()`.
- `app/Models/Category.php` — `activeOrdered()` now wraps its query in
  `Cache::remember()`; new `invalidateCache()`.
- `app/Controllers/Admin/CategoryController.php` — calls
  `Category::invalidateCache()` after create/update/delete.
- `app/Controllers/Customer/HomeController.php` — the four product
  rail queries wrapped in `Cache::remember()`.
- `app/Controllers/Admin/DashboardController.php` — the whole metrics
  bundle wrapped in one `Cache::remember()` call.
- `DEPLOYMENT.md` — a note on `storage/cache/` under file permissions
  (when, if ever, to manually clear it after a deploy).
- `tests/Unit/Core/CacheTest.php` — 6 tests covering the cache
  primitives directly (memoization, `forget()`, expiry, `put()` +
  `remember()` interaction).
- `tests/Feature/Models/CategoryTest.php` — 3 tests covering
  `activeOrdered()`'s active-only filtering, that it's actually served
  from cache between calls, and that `invalidateCache()` makes the
  next call see fresh data.

## 3. How to test

```bash
rm -f storage/cache/*.cache   # start clean
curl -s -o /dev/null http://localhost/           # populates the cache
ls storage/cache/                                 # 5 files: categories + 4 home rails
stat -c '%Y' storage/cache/*.cache                 # unchanged on a second request within the TTL
```

Edit any category in the admin panel and reload the homepage - the
change is visible immediately, not after an hour.

## 4. Live verification performed

- Confirmed a fresh homepage request writes 5 cache files (categories
  + 4 product rails), and a second request within the TTL leaves every
  file's mtime unchanged - proof the cached path is actually being
  taken, not just that the code compiles.
- Directly inspected each cache file's deserialized contents against
  the real database state to confirm correctness, not just presence.
- Exercised the full invalidation path through the real admin UI:
  renamed a category and toggled its `is_active` flag via
  `POST /admin/categories/{id}`, and confirmed the homepage's cached
  category list reflected both changes on the very next request - no
  wait, no manual cache clear.
- Verified `Cache`'s core semantics directly: `remember()` calls its
  callback exactly once across two calls with the same key; `forget()`
  forces the next `remember()` to recompute; an already-expired entry
  (`put()` with a negative TTL) is correctly treated as a miss and
  recomputed rather than served stale; `put()` followed by
  `remember()` returns the pre-loaded value without ever invoking the
  callback.
- Confirmed the admin dashboard's cache key format
  (`admin.dashboard.{today}`) matches what the controller actually
  generates by computing the same `sha1()` independently and finding
  the exact file.
- Ran the full PHPUnit suite three times in a row (95 tests, up from
  86, identical results each run), `composer lint`, and `composer cs`
  (0 errors/warnings).

## 5. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| An admin's category edit doesn't show up on the storefront right away | `Admin\CategoryController`'s action didn't reach `Category::invalidateCache()` - most likely a validation failure or an early `$this->back()` return before the mutating call, or a new mutating action was added without the same call | Every action in `Admin\CategoryController` that changes what `activeOrdered()` returns must call `Category::invalidateCache()` right after the mutation succeeds, same as `store()`/`update()`/`destroy()` do |
| A newly added/priced/on-sale product doesn't appear on the homepage for a few minutes | Expected (section 1) - the homepage product rails use a 5-minute time-based TTL with no explicit invalidation hook | Not a bug; wait out the TTL, or manually `rm storage/cache/*.cache` if immediate visibility is needed for a specific launch |
| `storage/cache/` fills up with stale-looking files after code changes to a cached query | Files don't disappear on their own until their TTL passes and something calls `remember()` for that exact key again - an old file for a key nothing requests anymore just sits there harmlessly until then | Harmless; `rm storage/cache/*.cache` clears everything unconditionally and is always safe to run (see `DEPLOYMENT.md` section 5) |

## 6. Closing note

This is the last of the 8 items from the "anything you can add"
follow-up (Modules 22-29): automated tests, CI + deployment docs, a
security review pass, RMA, abandoned-cart emails, multi-currency
display + state tax, vendor tiers, and now caching. See `README.md`'s
Production Readiness section for the full list with a one-line summary
of each.
