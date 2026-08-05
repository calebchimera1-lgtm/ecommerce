# Module 14 — Security Hardening Pass & Final QA

## 1. Explanation

The final module: a systematic security audit across everything built
in Modules 1-13, fixes for what it found, and a full regression sweep
of every route in the application. Unlike prior modules, this one adds
no new customer-facing feature - its job is to verify the other
fourteen modules hold up under scrutiny, and to close what doesn't.

### Audit method

Three areas large enough to need dedicated attention were delegated to
background research agents running in parallel against the real
codebase (read-only, no edits) so each could go deep without
diluting the others:

1. **Customer-side IDOR** - every controller action that loads an
   order/address/review/cart-item/wishlist row by a route parameter,
   checked for ownership scoping to the logged-in user.
2. **Admin-side cross-resource-type confusion + unescaped output** -
   whether staff/customer account IDs can be loaded through each
   other's routes, whether PO line-item and blog-comment IDs are
   scoped to their parent resource, and a sweep of every view for
   string output that bypasses the `e()` escaping helper.
3. **Price/quantity tampering** - whether any monetary value in the
   cart → coupon → checkout → order pipeline is ever trusted from
   client input rather than recomputed server-side from the database.

All three came back clean - no issues found. That's not a formality:
each of those areas is exactly where a hand-rolled MVC with 14 modules
of accumulated controllers is most likely to have a gap, and the
audits actually inspected the code (quoting file:line, tracing model
methods) rather than assuming from patterns.

The remaining checks - CSRF coverage, SQL injection, rate limiting,
mass assignment, session/cookie configuration, security headers,
`.htaccess` protections, secret handling - were done directly, mostly
by grepping the full breadth of `routes/`, `app/Models/`, and
`app/Controllers/` for the anti-pattern in question and confirming
zero matches, which is fast and exhaustive for those specific
concerns in a way spot-checking individual files isn't.

### Bugs found and fixed

**1. No rate limiting on registration, password-reset requests, or
resend-verification.** Login (both customer and admin) has used
`RateLimiter` since Module 2/3, but three other unauthenticated,
email-triggering endpoints never did:
`AuthController::register()`, `forgotPassword()`, and
`resendVerification()`. Each accepts an arbitrary email and (for the
latter two) sends mail to it - with no limiter, an attacker could
email-bomb any address by repeatedly hitting `/forgot-password` or
`/resend-verification`, or mass-create accounts via `/register`. Fixed
by extending the existing `RateLimiter` (no new mechanism) to all
three, keyed by IP rather than email (since the target email is
attacker-chosen, not the attacker's own identity) and counting every
attempt - not just failures - since these endpoints have no
"successful vs. failed" distinction the way login does. Verified live:
6 rapid registration attempts from one IP created exactly 5 accounts
and correctly rejected the 6th with a clear message; the same pattern
verified for password-reset requests.

**2. A coupon already applied to the cart could still be honored at
checkout after becoming invalid.** This is the most significant
finding. `CartController::applyCoupon()` validates a coupon fully
(active, date window, minimum order, usage limit, per-user limit)
*at the moment it's applied to the cart* - but `CartCalculator::summarize()`,
which computes the actual order total at checkout, was documented to
expect an already-validated coupon and never re-checked any of that
itself. `CheckoutController`'s own coupon lookup used the base
`Coupon::find()` (no validity filter at all), not
`Coupon::findValidByCode()`. The result: if a coupon expired, was
deactivated by an admin, or hit its usage limit in the time between a
customer applying it and completing checkout, the stale discount was
still silently applied to their order - an admin deactivating a
coupon mid-checkout, or a limited-use coupon being redeemed past its
limit under concurrent traffic, would both under-charge.

Verified the bug directly before fixing it: applied a coupon to a real
cart, deactivated it via the admin panel (simulating what an admin
revoking a coupon looks like), and confirmed - pre-fix - checkout
would have proceeded using the stale discount. Fixed by adding
`OrderPlacementService::assertCouponStillValid()`, called immediately
before the order total is computed (mirroring the existing
`assertStockAvailable()` pre-transaction check right above it): it
re-fetches the coupon by code via `findValidByCode()` and re-runs the
same min-order/usage-limit/per-user-limit checks `applyCoupon()`
already does, throwing `OrderPlacementException` - the same exception
type `CheckoutController` already catches and displays - if anything
no longer holds. Re-tested live after the fix: the same deactivate-mid-flow
scenario now correctly rejects checkout with "Your coupon is no longer
valid," creates no order, and leaves the customer's cart intact to
retry; re-activating the coupon and re-attempting checkout succeeds
normally with the discount applied and `used_count` incremented.

While in this code, also fixed `used_count`'s increment from a
PHP-computed read-modify-write (`Coupon::update($id, ['used_count' => $old + 1])` -
which under concurrent orders redeeming the same coupon could lose an
increment) to an atomic SQL `used_count = used_count + 1` via a new
`Coupon::incrementUsage()` method. Adjacent to the fix already in
progress, same file, same risk class (coupon usage accounting), worth
doing at the same time rather than leaving a known non-atomic write in
code just touched for a related reason.

**3. No script-execution block in `public/uploads/`.** `ImageUploader`
(Module 4) already validates the real MIME type via `fileinfo`,
confirms the file decodes as an image via `getimagesize()`, and always
writes a random server-generated filename with a server-determined
extension - the uploaded file's own name/extension is never trusted.
That's strong primary defense, but there was no independent
second-layer protection in the upload directory itself, standard
practice for anywhere user-supplied files land inside the web root.
Added `public/uploads/.htaccess` denying execution of
`.php`/`.phtml`/`.php3-7`/`.pht`/`.phar` and disabling the PHP engine
for that directory, so even a hypothetical future bypass of the
upload validation couldn't result in an executed script.

**4. `.gitignore` didn't cover the Module 13 testimonials upload
directory.** Every other upload subdirectory
(`products`/`categories`/`brands`/`avatars`/`blog`) has a
`/public/uploads/{dir}/*` + `!.gitkeep` pair; `testimonials/`
(added in Module 13 for customer photo uploads) was missed, meaning
any uploaded testimonial photo would have been committed to the repo
on the next broad `git add`. Added the matching gitignore entry and
`.gitkeep` before any real photo was ever uploaded and caught by it
(confirmed via `git status` - nothing was actually leaked).

**5. Minor PSR-12 formatting cleanup.** `vendor/bin/phpcs --standard=PSR12`
found 6 real formatting errors (all in `Admin\ReportController`'s
multi-line function call style) versus 0 in every other controller,
model, core, and service file - the rest of the ~450 findings across
the full `app/Views/` tree are line-length warnings inherent to mixing
HTML and PHP in templates, not defects. Ran `phpcbf` to auto-fix the
6 real ones; left the view-template line-length warnings alone as
expected noise for that file type.

### What was checked and found already correct

- **CSRF**: all 72 `POST` routes across `routes/web.php` and
  `routes/admin.php` carry `VerifyCsrfMiddleware` - confirmed by
  grepping for `$router->post(` entries missing it (zero found).
- **SQL injection**: every `Model::db()->query()` call (as opposed to
  `->prepare()`) uses a fully static SQL string with no interpolated
  variables; every dynamic `ORDER BY` is either passed through
  `Model::assertSafeColumn()` or built from a `match()` expression
  against a fixed whitelist (`Product::publicPaginate()`'s `$sort`
  parameter) - no request input ever reaches a query string directly.
- **Mass assignment**: every controller builds an explicit field
  array before calling `Model::create()`/`update()`; grepping for
  `::create($request->all())` or `::update($id, $request->all())`
  anti-patterns across every controller returns zero matches.
- **Session/cookie security**: HttpOnly, `SameSite=Lax`, `Secure`
  when HTTPS, session ID regeneration on login/logout, and a
  user-agent-binding integrity check were all already in place since
  Module 1/2.
- **Security headers**: CSP, `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`, and conditional HSTS are set
  on every response via `Response::securityHeaders()`, with a
  `.htaccess`-level backstop for `X-Content-Type-Options`/`X-Frame-Options`
  in case PHP-level headers are ever bypassed.
- **Sensitive directories**: `database/`, `config/`, and `storage/`
  each carry their own `Require all denied` `.htaccess`, independent
  of `public/`'s protection, so a misconfigured `DocumentRoot`
  pointing at the project root instead of `public/` still can't serve
  `.sql` dumps, `.env`-adjacent config, or logs.
- **Secrets**: `.env` is gitignored and was never committed (only
  `.env.example`, which has no real values); a full-history grep for
  hardcoded API keys/passwords in tracked PHP files found nothing.
- **Cross-boundary session behavior**: `AdminMiddleware` gates on the
  `dashboard.view` permission (which the seeded `customer` role never
  has), so a customer session hitting `/admin/*` gets 403, not 200
  with an empty dashboard.

## 2. Folder location / files delivered

No new features - fixes only:

```
public/uploads/.htaccess                  (new)
public/uploads/testimonials/.gitkeep      (new)
```

Updated: `app/Controllers/Customer/AuthController.php` (rate limiting
on register/forgotPassword/resendVerification),
`app/Services/Order/OrderPlacementService.php`
(`assertCouponStillValid()`, atomic usage increment),
`app/Models/Coupon.php` (`incrementUsage()`),
`app/Controllers/Admin/ReportController.php` (PSR-12 formatting),
`.gitignore` (testimonials upload path).

## 3. Routes

No new routes.

## 4. SQL

No schema changes.

## 5. Testing instructions

1. **Rate limiting**: submitted 6 rapid registration attempts from one
   IP - confirmed exactly 5 accounts were created and the 6th was
   rejected with "Too many signup attempts from this location."
   Repeated for `/forgot-password` with the same result and message.
2. **Coupon staleness**: created a real coupon via the admin panel,
   applied it to a real customer's cart, deactivated it via the admin
   panel (simulating the gap this bug exploited), then attempted
   checkout - confirmed it was rejected with "Your coupon is no longer
   valid," no order was created, and the cart was left intact.
   Reactivated the coupon and completed checkout normally - confirmed
   the discount applied correctly and `used_count` incremented by
   exactly 1.
3. **Full regression sweep**: hit every public route (home, shop,
   category/brand/search, product detail, all static pages, blog
   index/category/post, sitemap.xml, robots.txt, cart, register/login/forgot-password),
   every authenticated customer route (account dashboard, orders,
   reviews, profile, wishlist), and every admin route (all 21 admin
   sections built across Modules 3-13) - confirmed every single one
   returns its expected status code with no 500s, using the real
   session cookies from real logins rather than assuming from route
   definitions.
4. Ran `vendor/bin/phpcs --standard=PSR12` against the full `app/`
   tree and against just the logic layer (`Controllers`/`Models`/`Core`/`Services`/`Middleware`)
   separately, to distinguish real formatting defects from expected
   view-template line-length noise; fixed the 6 real ones found.

## 6. Bugs found while testing (and fixed before commit)

All five are detailed above under "Bugs found and fixed": missing
rate limiting on three auth-adjacent endpoints, the coupon
re-validation gap at checkout (the most significant finding of this
module), the non-atomic coupon usage counter, the missing
`public/uploads/.htaccess`, and the missing `.gitignore` entry for
testimonial uploads.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| "Too many signup/reset requests" appears sooner than expected during manual testing | `RATE_LIMIT_MAX_ATTEMPTS`/`RATE_LIMIT_DECAY_MINUTES` (`.env`) apply per-IP across register/forgot-password/resend-verification now, not just login | Expected behavior; wait out the decay window, or raise the limit in `.env` for a dev environment doing rapid manual testing |
| A coupon that worked when applied to the cart is rejected at checkout | The coupon was deactivated, expired, or hit its usage/per-user limit in between - `assertCouponStillValid()` is working as designed | Remove the coupon and re-apply a valid one, or ask an admin to review the coupon's status |
| Uploaded images 404 or won't display after pulling this module | Should not happen - `public/uploads/.htaccess` only blocks *script execution* (`.php` and friends), not static asset serving (`.jpg`/`.png`/`.webp` are untouched) | If it does happen, check the web server actually supports `.htaccess` overrides (`AllowOverride All` in the Apache vhost) - PHP's built-in dev server ignores `.htaccess` entirely and was used for all testing in this project, so this file's behavior is only exercised under real Apache/XAMPP |

## 8. Known limitations and recommendations for production

Honest gaps that a real production launch should address, not covered
by this module's scope:

- **No automated test suite.** `composer.json` has declared
  `phpunit/phpunit` as a dev dependency since Module 1, but no test
  files or `phpunit.xml` were ever created - every module's
  verification in this project was live, manual testing against a
  real database via the actual UI (documented in each module's "6.
  Bugs found" section), not an automated regression suite. That
  approach caught real bugs (including everything in this module) but
  doesn't leave behind anything that reruns itself. A production fork
  of this project should add PHPUnit coverage for the pricing/coupon/order
  pipeline in particular, since that's the highest-consequence logic
  in the app.
- **Payment gateways need real credentials before launch.** Stripe and
  PayPal (Module 7) have genuine REST integrations but are inert
  without real API keys in `.env`; M-Pesa's callback endpoint was
  explicitly left unimplemented (documented in Module 7) since it
  requires a publicly reachable, signature-verified webhook this
  local/sandboxed build has no way to expose or test - that callback
  endpoint needs to be built and its signature verification
  implemented and tested against Safaricom's sandbox first.
- **SMTP needs real credentials.** `Mailer` (Module 2) falls back to
  writing emails to `storage/logs/mail-*.log` when `MAIL_HOST` is
  blank - correct for development, but production needs a real SMTP
  provider configured or transactional emails (order confirmations,
  password resets, verification links) never reach anyone.
- **HSTS is conditional on `APP_DEBUG=false`,** which is correct, but
  only actually protects anything once the site is served over real
  HTTPS with a valid certificate - confirm the production vhost
  terminates TLS before relying on it.
- **Rate limiting is IP-based and in-database**, not a dedicated
  in-memory store (Redis/Memcached) - fine at this project's scale,
  but a high-traffic production deployment behind a shared IP (NAT,
  corporate proxy) would throttle multiple legitimate users together;
  consider a proxy-level WAF/rate-limiter for internet-facing
  production traffic in addition to this application-level one.
- **Dependency freshness**: `dompdf/dompdf` and `phpmailer/phpmailer`
  were pinned to their latest available versions at the time each was
  added (Modules 2 and 12 respectively); run `composer audit`
  periodically post-launch, as this project's build process didn't
  include recurring dependency scanning.

## 9. Project status

This completes the 14-module build plan. Kymera Collection now has a
full customer storefront (browsing, cart, checkout, order tracking,
reviews, wishlist, blog) and a full admin panel (catalog, orders,
customers, staff/RBAC, inventory/suppliers/purchase orders, reports/exports,
expenses, blog/testimonials, audit log) on a hand-rolled PHP 8.2+ MVC
core with MySQL 8/PDO, verified module-by-module against a real
database through the actual UI rather than assumed correct from code
review alone.
