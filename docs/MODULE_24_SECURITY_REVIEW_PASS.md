# Module 24 — Security Review Pass

## 1. Explanation

Module 14 (way back in the original 14-module build) was already
called "Security hardening pass & final QA" - this module is a fresh
audit against the codebase as it stands now, ten modules later
(15-23), across five areas: CSRF coverage, auth/permission gating, SQL
injection surface, file upload validation, and session handling. The
method throughout was verification, not assumption - every claim below
is backed by a grep, a count, or a read of the actual implementation,
not "this pattern is usually safe so it's probably fine here too."

### CSRF: exhaustively verified, not sampled

Every `POST`/`PUT`/`PATCH`/`DELETE` route across all three route files
(`routes/web.php`, `routes/admin.php`, `routes/vendor.php`) was
extracted and checked for `VerifyCsrfMiddleware::class` in its
middleware array. The counts matched exactly:

| File | State-changing routes | With CSRF middleware |
|---|---|---|
| `routes/web.php` | 28 | 28 |
| `routes/admin.php` | 57 | 57 |
| `routes/vendor.php` | 11 | 11 |

96 state-changing routes across 9 modules' worth of route
registration, 100% coverage.

### Permission gating: no typos, no accidental privilege grants

Every `PermissionMiddleware` slug referenced in `routes/admin.php`
(18 distinct slugs) was cross-checked against the real
`permissions.slug` values in the database - an exact match, meaning no
route accidentally references a slug that doesn't exist (which would
silently deny everyone - a fail-safe bug, annoying but not a security
hole) or, worse, a slug that exists but grants access to the wrong
resource (which would be a real privilege-escalation bug - e.g. a
vendor-management route accidentally gated by `products.manage`
instead of `vendors.manage`). None of the latter were found.

One informational note, not a fix: `/admin/dashboard` is gated by
`AdminMiddleware` alone (any authenticated staff account), not by
`PermissionMiddleware` with the `dashboard.view` slug that exists in
the permissions table and that the sidebar nav config already checks
before showing the Dashboard link. Today this is harmless - all three
staff roles (Super Admin, Manager, Support) hold `dashboard.view`, so
there is no account that can authenticate to `/admin/*` at all but
shouldn't see the dashboard. It's worth knowing about if a future,
narrower staff role is ever introduced without that permission: such a
role's users could still reach `/admin/dashboard` directly by URL even
though the nav wouldn't link to it for them. Not changed in this pass
- treating `dashboard.view` as purely a nav-visibility permission
(rather than a hard access gate on a page that shows nothing
role-specific) is a defensible design choice, and changing route
behavior without being asked to risks contradicting an intentional
decision rather than fixing a bug.

### SQL injection: parameterized throughout, checked at the seams

Grepped for every place SQL is built with runtime values instead of
prepared-statement placeholders. Every hit resolved to one of three
safe patterns already established by the codebase:

- **Dynamic `ORDER BY`/column names** always go through
  `Model::assertSafeColumn()`, which throws on anything that isn't a
  bare identifier - and every call site passes a hardcoded,
  developer-written string, never request input.
- **Dynamic `WHERE` fragments** (every `buildXWhere()` helper across
  the model layer) interpolate only server-built condition strings;
  every actual *value* is bound via a named placeholder.
- **The one genuinely dynamic case**, `Validator::failsUnique()`
  building a `unique:table,column` check, takes its table/column names
  from the validation *rule string* - written by the developer calling
  `new Validator($data, $rules)`, never from `$data` (the user-supplied
  half). It additionally strips non-identifier characters as a second
  layer, even though the first layer (rules aren't user input) already
  makes it safe.

The one place `$_GET` is read directly instead of through `Request`
(`customer/layouts/site.php`'s search box pre-fill) is passed through
`e()` before being echoed into an HTML attribute - not a SQL context
at all, and not an XSS gap either.

### XSS: spot-checked every risky field name across all views

Grepped every view for unescaped output (`<?= $var ?>` without `e()`)
of the field names most likely to carry attacker-influenced text
(`name`, `comment`, `title`, `description`, `content`, `message`,
`note`, `reason`, `email`, `first_name`, `last_name`, `address`,
`city`, `phone`) - zero matches. Every other unescaped `<?=` found
across the view tree resolves to a genuinely safe value: integers
(counts, loop indices, ratings), or a ternary between two
developer-written literal strings (`'active' : ''`, `'solid' :
'regular'`, `'Edit X' : 'New X'`) - never raw user content.

### File uploads: one real defense-in-depth gap found and fixed

`ImageUploader::store()` was already solid going in - MIME sniffed
server-side via `fileinfo` (never trusts the client's `Content-Type`),
extension chosen from a hardcoded MIME-to-extension map (never the
uploaded filename), `getimagesize()` confirms the file actually
decodes as an image, filenames are random hex, and
`move_uploaded_file()` blocks local-file-inclusion via a crafted
`$_FILES` array. `ImageUploader::delete()` was weaker: it checked that
the given web path started with the string `/uploads/`, then built a
filesystem path and called `unlink()` directly. A string-prefix check
does not stop `/uploads/../../etc/passwd` - that string *does* start
with `/uploads/`; the `..` segments only matter once the OS resolves
the path, which happens after the check already passed.

**No caller in the current codebase is exploitable** - every one of
the seven call sites (`Vendor\ProductController`, `Vendor\ProfileController`,
`Admin\ProductController`, `Admin\BlogPostController` (x2),
`Admin\TestimonialController` (x2)) passes a value read straight from
a database column, and the only code that ever writes those columns is
`ImageUploader::store()` itself, which always produces
`/uploads/{subdir}/{random-hex}.{ext}`. This is fixed anyway, as
defense-in-depth: if any future code path ever passes a
less-trusted value into `delete()` - a bug, a new admin bulk-import
feature, anything - the missing containment check would turn into a
real arbitrary-file-delete vulnerability the moment that happened.
Fixed by resolving both the uploads root and the target path with
`realpath()` and confirming the target is actually contained within
the uploads directory before unlinking; a path that traverses out (or
one that doesn't resolve to a real file at all) is silently ignored,
matching the prior behavior's "file doesn't exist, nothing to do"
outcome exactly for every legitimate call.

### Session handling: fixation protection actually wired up, not just available

`Session::regenerate()` exists and is genuinely called at the moment
that matters - `Auth::login()` (shared by all three account types:
customer, admin, vendor) regenerates the session ID *before* setting
the auth session key, and `Auth::logout()` destroys and regenerates
again. Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure`
whenever the request is over HTTPS; a user-agent hash is bound to the
session and a mismatch destroys and restarts it. The "remember me"
cookie stores only a SHA-256 hash of its token server-side (a DB leak
alone can't forge a valid cookie) and is compared with `hash_equals()`,
not `===`. CSRF token comparison (`Csrf::verify()`) also uses
`hash_equals()`. Password verification uses `password_verify()`
throughout (checked all three `AuthController`s), never a manual
comparison. Password reset tokens are `random_bytes(32)`, stored only
as a SHA-256 hash, expire after one hour, and the "forgot password"
endpoint always shows the same success message whether or not the
email exists - no account-enumeration oracle. Login, registration, and
password-reset-request endpoints are all rate-limited
(`RateLimiter`, DB-backed, `login_attempts` table) across all three
account types. Uncaught exceptions log full details server-side
always, but only echo a stack trace to the visitor when
`APP_DEBUG=true` - verified by reading `App::configureErrorHandling()`
directly, not assumed from the `.env.example` comment.

## 2. Folder location / files delivered

No new files - this module is an audit, not new functionality. One
production file was hardened:

```
app/Services/Upload/ImageUploader.php   (delete() path-traversal fix)
tests/Unit/Services/ImageUploaderTest.php   (new - regression coverage for the fix)
```

## 3. Routes

None added or changed.

## 4. SQL

None.

## 5. Testing instructions

Every claim in section 1 was verified directly rather than asserted:

1. CSRF coverage counted exactly as described (grep + `wc`-style count
   comparison) for all three route files - see the table above.
2. Permission slugs extracted from `routes/admin.php` via regex and
   diffed against `SELECT slug FROM permissions` - exact match, no
   extras on either side beyond `dashboard.view` (used only in the nav
   config, discussed above).
3. Grepped the whole `app/` tree for `sprintf`/raw string SQL patterns
   and for direct `$_GET`/`$_POST`/`$_REQUEST` usage outside
   `App\Core\Request` and `App\Core\Model` themselves - every hit
   reviewed individually and traced to a safe pattern.
4. Grepped every view for unescaped output of the highest-risk field
   names (see the XSS section above) - zero matches; the remaining
   unescaped-output occurrences across the whole view tree were each
   manually inspected and confirmed to be integers or
   developer-literal ternaries, never user content.
5. **The `ImageUploader::delete()` fix was verified with a real
   traversal attempt, not just read for correctness**: wrote a canary
   file to `storage/logs/traversal-canary.txt`, called
   `ImageUploader::delete('/uploads/../../storage/logs/traversal-canary.txt')`,
   and asserted the canary file still exists afterward
   (`ImageUploaderTest::test_delete_does_not_escape_the_uploads_directory_via_traversal`).
   Also verified the fix doesn't break legitimate deletion - a file
   genuinely inside `public/uploads/products/` is still removed
   correctly by the same code path.
6. Ran the full test suite (60 tests, 131 assertions - the 3 new
   `ImageUploaderTest` cases added to Module 22's existing 57) and
   `composer cs` after the fix - both clean.
7. Traced `Session::regenerate()`'s call sites directly in
   `app/Core/Auth.php` and confirmed all three `AuthController::login()`
   methods (customer, admin, vendor) call the shared `Auth::login()`
   rather than reimplementing session handling separately.

## 6. Bugs found while testing (and fixed before commit)

One real bug, described in full above: `ImageUploader::delete()`'s
string-prefix check on `/uploads/` does not stop a path-traversal
sequence from resolving outside the uploads directory once the
filesystem resolves the `..` segments. No current call site is
exploitable (every one passes a value the app itself generated and
stored, never raw user input), but the missing containment check was
a latent gap inconsistent with how carefully-defended the rest of
`ImageUploader` already is. Fixed with a `realpath()`-based
containment check; verified fixed with a live traversal attempt
against a real canary file (section 5, item 5), not just by reading
the new code and reasoning it should work.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| A previously-working image delete silently does nothing after this update | The path passed to `ImageUploader::delete()` doesn't resolve to a real, existing file under `public/uploads/` (e.g. already deleted, or the uploads directory was moved/renamed) | `realpath()` returns `false` for anything that doesn't exist, which `delete()` now treats as "nothing to do" - same as the old `is_file()` check's effective behavior for a legitimate, already-correct path |
| `/admin/dashboard` is reachable by a staff account whose role you expected to be blocked from it | `dashboard.view` isn't enforced at the route level, only used to hide the nav link (see section 1) - this is a known, accepted characteristic, not a bug | If a future role genuinely needs to be blocked from the dashboard itself (not just its nav link), add `[PermissionMiddleware::class, 'dashboard.view']` to that route - a deliberate follow-up, not something this pass changed unasked |

## 8. Next module

**Module 25 — Returns/refunds (RMA) workflow**: customer-initiated
return requests on delivered orders, admin approval/rejection, and
refund tracking tied to `orders.payment_status = 'refunded'` and the
`payments` table - vendor-aware where a return touches a vendor's
sub-order, so a return on a marketplace item correctly affects that
vendor's payout accounting rather than the platform's alone.
