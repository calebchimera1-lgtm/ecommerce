# Module 2 — Customer Authentication

## 1. Explanation

Full customer-facing auth: registration, email verification (with
resend), login, logout, forgot/reset password, "remember me", and
account-area gating — all built on Module 1's `users`, `password_resets`,
and `login_attempts` tables plus the `Session`/`Csrf`/`Validator` core.

New core infrastructure added in this module:

- **`App\Core\Auth`** — session-based login state, plus a "remember me"
  cookie (`user_id|plainToken`, only a SHA-256 hash of the token is
  stored in `users.remember_token`) that transparently re-establishes
  the session on a later visit (`Auth::attemptResumeFromCookie()`,
  called once per request from `App::run()`). Session ID is regenerated
  on both login and logout to prevent session fixation.
- **`App\Core\RateLimiter`** — throttles login attempts per identifier
  (lowercased email) using the `login_attempts` table; default 5 failed
  attempts per 15-minute window (configurable via `.env`).
- **`App\Core\Uuid`** — RFC 4122 v4 UUID generator, used for `users.uuid`.
- **`App\Middleware\{Guest,Auth,VerifyCsrf}Middleware`** — route guards:
  `GuestMiddleware` keeps logged-in users off auth pages, `AuthMiddleware`
  protects `/account`, `VerifyCsrfMiddleware` enforces the synchronizer
  CSRF token on every POST/PUT/PATCH/DELETE route it's attached to.
- **`App\Services\Notification\Mailer`** — PHPMailer/SMTP wrapper. When
  `MAIL_HOST` is empty (the default for local dev), emails are written
  to `storage/logs/mail-*.log` instead of sent, so the verification and
  password-reset flows are fully testable without real SMTP credentials.

### Security decisions worth calling out

- **Passwords**: hashed with `password_hash()` using the algorithm from
  `config('security.hash_algo')` (Argon2id by default).
- **Verification & reset tokens are never stored in plaintext.** The
  emailed link contains the plain token; the database stores only
  `hash('sha256', $token)`. A database leak alone cannot be used to
  verify an account or reset a password.
- **User enumeration resistance**: `POST /forgot-password` always shows
  the same "if an account exists..." message regardless of whether the
  email is registered.
- **Login requires a verified email.** Registration does not auto-login;
  the account is unusable until the verification link is clicked (or a
  new one requested via "Resend verification email" on the login page).
- **Rate limiting** is per-account (by email), so it can't be bypassed
  by rotating IPs, and it's separate from any future IP-based throttling
  on other endpoints.

## 2. Folder location / files delivered

```
app/Core/Auth.php
app/Core/RateLimiter.php
app/Core/Uuid.php
app/Middleware/GuestMiddleware.php
app/Middleware/AuthMiddleware.php
app/Middleware/VerifyCsrfMiddleware.php
app/Models/Role.php
app/Models/User.php
app/Models/PasswordReset.php
app/Services/Notification/Mailer.php
app/Controllers/Customer/AuthController.php
app/Controllers/Customer/DashboardController.php
app/Views/customer/layouts/auth.php
app/Views/customer/auth/register.php
app/Views/customer/auth/login.php
app/Views/customer/auth/forgot-password.php
app/Views/customer/auth/reset-password.php
app/Views/customer/account/dashboard.php      (placeholder, full module later)
app/Views/partials/alerts.php                  (shared flash/error renderer)
app/Views/emails/verify-email.php
app/Views/emails/reset-password.php
app/Views/errors/419.php
routes/web.php                                 (updated with auth routes)
app/Core/App.php                               (updated: resumes remember-me cookie)
```

Two pre-existing Module 1 files were fixed as part of building/testing
this module (see "Bugs found while testing" below):

- `app/Core/Validator.php`
- `app/Core/Session.php`

## 3. Routes

| Method | Path | Middleware | Controller action |
|---|---|---|---|
| GET | `/register` | Guest | `AuthController::showRegister` |
| POST | `/register` | Guest, CSRF | `AuthController::register` |
| GET | `/login` | Guest | `AuthController::showLogin` |
| POST | `/login` | Guest, CSRF | `AuthController::login` |
| POST | `/logout` | Auth, CSRF | `AuthController::logout` |
| GET | `/forgot-password` | Guest | `AuthController::showForgotPassword` |
| POST | `/forgot-password` | Guest, CSRF | `AuthController::forgotPassword` |
| GET | `/reset-password/{token}` | Guest | `AuthController::showResetPassword` |
| POST | `/reset-password` | Guest, CSRF | `AuthController::resetPassword` |
| GET | `/verify-email/{token}` | — | `AuthController::verifyEmail` |
| POST | `/resend-verification` | CSRF | `AuthController::resendVerification` |
| GET | `/account` | Auth | `DashboardController::index` (placeholder) |

## 4. SQL

No schema changes — this module uses the `users`, `password_resets`,
and `login_attempts` tables exactly as defined in Module 1's
`database/kymera_collection.sql`.

## 5. Testing instructions

This module was verified end-to-end against a **real MariaDB 10.11**
instance (MySQL-compatible) in the build environment, not just linted.
To repeat the same test locally:

1. Follow Module 1's setup (import the schema, `composer install`,
   configure `.env`).
2. Serve the app: `php -S localhost:8000 -t public`.
3. **Register**: `GET /register`, submit the form (or `curl` it with a
   CSRF token scraped from the page — see below). You should land back
   on `/login` with a "check your email" success message.
4. **Check the verification email**: since `MAIL_HOST` is empty by
   default, look in `storage/logs/mail-YYYY-MM-DD.log` for the
   `verify-email` entry and its `/verify-email/{token}?email=...` link.
5. **Try logging in before verifying** → should fail with "Please
   verify your email address before logging in."
6. **Visit the verification link** → redirects to `/login` with a
   success message; `users.email_verified_at` is now set.
7. **Log in** (optionally check "Remember me") → redirects to
   `/account`, which shows the logged-in user's name/email. Confirm a
   `kymera_remember` cookie was set and `users.remember_token` is
   populated (only its SHA-256 hash, never the plaintext cookie value).
8. **Log out** → `/account` now redirects to `/login`; confirm
   `users.remember_token` was cleared.
9. **Forgot/reset password**: `POST /forgot-password` with a registered
   email → check `storage/logs/mail-*.log` for the reset link → submit
   `POST /reset-password` with a new password → log in with the new
   password.
10. **Rate limiting**: submit 5 wrong passwords for the same account →
    the 6th attempt (within 15 minutes) returns "Too many failed login
    attempts" without even checking the password.
11. **CSRF**: submit any POST route without the `kymera_csrf_token`
    field (or with a wrong value) → expect HTTP 419.

A quick `curl` recipe for step 3 (scrape the CSRF token, then post):
```bash
curl -sc jar.txt http://localhost:8000/register -o register.html
TOKEN=$(grep -oP 'name="kymera_csrf_token" value="\K[^"]*' register.html | head -1)
curl -sb jar.txt -c jar.txt -i -X POST http://localhost:8000/register \
  -d "kymera_csrf_token=$TOKEN" -d "first_name=Jane" -d "last_name=Doe" \
  -d "email=jane@example.com" -d "password=SuperSecret1" -d "password_confirmation=SuperSecret1"
```

## 6. Bugs found while testing (and fixed before commit)

Building this module surfaced three real defects in Module 1 code that
hadn't been exercised by its own (much simpler) smoke test. All three
were caught by actually running the flows against a live database
rather than just linting, and are fixed in this commit:

| Bug | File | Symptom | Fix |
|---|---|---|---|
| `min`/`max` validation rules used numeric comparison for any numeric-looking string | `app/Core/Validator.php` | An all-digit password like `"1234567"` (7 chars) would pass a `min:8` rule, because `is_numeric()` was true and `1234567 < 8` is false | `min`/`max` now only use numeric comparison when the field's rule set also declares `numeric` or `integer`; otherwise they measure string length |
| `Session::destroy()` didn't reset the internal `$started` flag | `app/Core/Session.php` | `Auth::logout()` calls `Session::destroy()` then `Session::start()` to establish a fresh session — but `start()` silently no-opped because the static flag still said "already started", leaving no active session and throwing a `session_regenerate_id(): no active session` warning on the next `Session::regenerate()` | `destroy()` now resets `self::$started = false` so the following `start()` actually re-initializes |
| Wrong relative path to the shared alerts partial | `app/Views/customer/layouts/auth.php` | `dirname(__DIR__)` from `customer/layouts/` only goes up to `customer/`, not `Views/`, so every auth page (`/login`, `/register`, ...) 500'd looking for `Views/customer/partials/alerts.php` | Changed to `dirname(__DIR__, 2)` to correctly resolve `Views/partials/alerts.php` |

A fourth item, `composer.json` declaring an autoloaded
`app/Helpers/functions.php` that didn't exist yet, was caught and fixed
during Module 1's own testing (see that module's docs) — the global
`config()`, `url()`, `old()`, `csrf_field()`, and `e()` helpers used
throughout this module's views live there.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `419` on every form submit | Missing/incorrect `kymera_csrf_token` field, or the session cookie wasn't sent back (e.g. testing with `curl` but forgetting `-b`/`-c` to persist cookies across requests) | Include the hidden CSRF field from the form, and reuse the same cookie jar for the GET (which mints the token) and the following POST |
| Registered but can never log in | Verification email never "arrives" | Check `storage/logs/mail-*.log` — in local dev (`MAIL_HOST` empty) mail is logged, not sent |
| "Too many failed login attempts" appears immediately on a fresh account | Left-over failed attempts from earlier manual testing of the same email, still inside the 15-minute decay window | Wait for the window to elapse, or `DELETE FROM login_attempts WHERE identifier = 'you@example.com';` in dev |
| Remember-me cookie doesn't survive a browser restart | Cookie `secure` flag is set whenever the request looks like HTTPS; if you're testing on `https://localhost` with a self-signed cert some browsers reject the cookie | Use plain `http://localhost` for local dev, or trust the dev cert |
| `curl -X POST -L` doesn't show the flash message you expect | `curl`'s `-X` forces the *same* HTTP method on every hop of a followed redirect, so a POST redirect gets re-POSTed instead of turning into a GET like a real browser does | Don't combine `-X POST` with `-L`; issue the POST, then a separate GET, to mirror real browser behavior |

## 8. Next module

**Module 3 — Admin authentication & RBAC middleware**: a separate
`/admin/login` for staff (Super Admin / Manager / Support roles),
permission-checking middleware built on the `role_permissions` matrix
from Module 1, and the base admin layout. Waiting for confirmation to
proceed.
