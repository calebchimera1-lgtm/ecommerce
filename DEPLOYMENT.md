# Deployment Guide

This covers taking Kymera Collection from a cloned repository to a
running production instance. It assumes familiarity with basic Linux
server administration; it does not assume familiarity with this
specific codebase.

## 1. Server requirements

- **PHP 8.2+** with extensions: `curl`, `fileinfo`, `gd`, `json`,
  `mbstring`, `pdo`, `pdo_mysql`
- **MySQL 8.0+ or MariaDB 10.6+**
- **Composer 2.x**
- A web server (Apache with `mod_rewrite`, or Nginx) able to point its
  document root at `public/`

Nothing else is required - there's no Node/npm build step, no queue
worker, no cache daemon (see Module 29 in the README's Production
Readiness section for planned caching work). A working `cron` is
needed for the one scheduled job this app does have (section 10).

## 2. Get the code and install dependencies

```bash
git clone <your-fork-or-remote-url> kymera-collection
cd kymera-collection
composer install --no-dev --optimize-autoloader
```

Use `--no-dev` in production - it skips PHPUnit/PHP_CodeSniffer, which
are development-only tools.

## 3. Configure the environment

```bash
cp .env.example .env
```

Then edit `.env`. Every key is documented inline in the file; the ones
that matter most for a first deploy:

| Key | What it controls |
|---|---|
| `APP_ENV` | Set to `production`. This also controls whether debug details leak into error pages. |
| `APP_DEBUG` | Set to `false` in production - `true` exposes stack traces to visitors. |
| `APP_URL` | Your real domain, no trailing slash. Used for absolute links in emails and the sitemap. |
| `APP_KEY` | A 32-byte random key. Generate one with `php -r "echo bin2hex(random_bytes(32));"` and paste the output in. |
| `DB_*` | Your production database credentials (see step 4). |
| `MAIL_*` | See "Mail" below - leave `MAIL_HOST` blank to run without real email sending. |
| Payment gateway keys | See "Payment gateways" below. |

**Never commit `.env`** - it's already gitignored. Set real secrets
directly in the file on the server, or via your hosting platform's
secret-management feature if it injects environment variables instead
of a file.

## 4. Set up the database

Create an empty database and user:

```sql
CREATE DATABASE kymera_collection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kymera'@'localhost' IDENTIFIED BY 'a-real-password';
GRANT ALL PRIVILEGES ON kymera_collection.* TO 'kymera'@'localhost';
FLUSH PRIVILEGES;
```

Import the full schema (this includes every table through Module 21 -
vendor marketplace, orders, reviews, everything):

```bash
mysql -u kymera -p kymera_collection < database/kymera_collection.sql
```

Match `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in `.env` to what you
just created.

### If you're upgrading an existing deployment instead of a fresh install

Don't re-import `kymera_collection.sql` - it will fail with
"duplicate table" errors. Instead, apply only the migration files your
database hasn't seen yet, **in numeric order**:

```bash
for f in database/migrations/*.sql; do
  echo "Applying $f"
  mysql -u kymera -p kymera_collection < "$f"
done
```

Each migration file documents what it does at the top. They are
**not** idempotent (no `IF NOT EXISTS` guards) - running one that's
already applied fails loudly with a duplicate-column/duplicate-table
error, which is your signal to skip it, not a sign of real trouble.
Track which migrations you've applied (a plain text note, or a
`schema_migrations` table if you want to be more rigorous) so you
don't have to rely on trial-and-error against production.

## 5. File permissions

The web server user needs write access to:

```bash
chmod -R 775 storage/logs storage/cache storage/sessions public/uploads
chown -R www-data:www-data storage/logs storage/cache storage/sessions public/uploads
```

(Adjust `www-data` to whatever user your web server actually runs as -
`nginx`, `apache`, etc.)

`storage/cache/` (Module 29) holds the file-based cache - category
data (1 hour TTL, invalidated immediately on any admin category edit)
and homepage/admin-dashboard aggregates (5 minute TTL, time-based
only). Every entry expires and self-heals on its own, so there's
nothing to clear on a routine deploy; `rm storage/cache/*.cache` is
only worth running after a deploy that changes what a cached query
*returns* for the same inputs (a schema migration touching
`categories`/`products`/`orders`, or a code change to one of the
cached queries themselves) - safe to run any time, since the next
request just recomputes and re-caches.

## 6. Point the web server at `public/`

**Apache**: set `DocumentRoot` to the `public/` directory and ensure
`AllowOverride All` (or equivalent) so `public/.htaccess` is honored -
that file handles routing everything through `public/index.php` and
blocks direct access to dotfiles.

A root-level `.htaccess` also exists as a safety net for
misconfigured setups where the document root was accidentally pointed
at the project root instead of `public/` - it redirects into
`public/`, but don't rely on it; point the document root correctly.

**Nginx** (no `.htaccess` support - use a server block instead):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/kymera-collection/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Use HTTPS in production (Let's Encrypt via certbot is the usual free
option) - login forms, checkout, and every session cookie assume a
secure context.

## 7. Payment gateways (configuration only - bring your own credentials)

The gateway abstraction (`app/Services/Payment`) already supports
Stripe, PayPal, and M-Pesa, plus Cash on Delivery (`cod`, always
available, needs no credentials). Each real gateway activates itself
automatically once its `.env` keys are filled in -
`PaymentGatewayManager::isConfigured()` checks for their presence, and
the checkout page shows every gateway but disables selecting one
that isn't configured. **This repository intentionally ships with
these blank** -
no real payment credentials are included or should ever be committed.

| Gateway | `.env` keys | Where to get them |
|---|---|---|
| Stripe | `STRIPE_PUBLIC_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | [Stripe Dashboard → Developers → API keys](https://dashboard.stripe.com/apikeys). Use test-mode keys first (they're prefixed `pk_test_`/`sk_test_`) and confirm checkout end-to-end before switching to live keys. |
| PayPal | `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE` | [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/) → Apps & Credentials. Leave `PAYPAL_MODE=sandbox` until you've verified a full test purchase. |
| M-Pesa (Daraja) | `MPESA_ENV`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_CALLBACK_URL` | [Safaricom Daraja Portal](https://developer.safaricom.co.ke/) - register an app, use the sandbox shortcode/passkey Safaricom provides for testing. `MPESA_CALLBACK_URL` must be a publicly reachable HTTPS URL on your deployed domain (M-Pesa's servers call it directly - it cannot be `localhost`). |

After setting real keys, verify with a real (or sandbox) purchase all
the way through `/checkout` before considering the gateway live -
Module 7 and Module 18's manual test plans (see `docs/`) describe the
full flow to exercise.

## 8. Mail (SMTP)

Leave `MAIL_HOST` blank to run without real email - `App\Services\Notification\Mailer`
detects this and writes every email that would have been sent to
`storage/logs/mail-*.log` instead, which is how this entire project
was built and tested. For real delivery, fill in:

```
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="Your Store Name"
```

Any standard SMTP provider works (Postmark, SendGrid, Mailgun, Amazon
SES, your own mail server) - `PHPMailer` (already a dependency) talks
plain SMTP, no provider-specific SDK needed. Send a real test email
(e.g. trigger a password reset or vendor approval email) after
configuring, and check it actually arrives, not just that
`storage/logs/mail-*.log` stops receiving new entries.

## 9. Run the test suite before every deploy

```bash
composer install  # with dev dependencies this time
composer test
```

See `docs/MODULE_22_AUTOMATED_TEST_SUITE.md` for what's covered and
how the test database isolation works. CI (`.github/workflows/ci.yml`)
runs this automatically on every push/PR against a throwaway MySQL
service container - treat a red CI run as a hard blocker, not a
suggestion.

## 10. Scheduled jobs (cron)

This app has no background scheduler daemon or queue worker - anything
time-based runs as a plain CLI script under `bin/`, invoked by the
system crontab. There's one so far:

| Script | Purpose | Suggested schedule |
|---|---|---|
| `bin/send-abandoned-cart-emails.php` | Emails a customer whose logged-in cart has sat untouched past `settings.abandoned_cart_threshold_hours` (seeded to 24h) a recovery link back to `/cart`. Safe to run more often than the threshold - a cart is only ever emailed once per idle spell, so an extra run just finds nothing new. | Hourly |

Add an entry to the deploy user's crontab (`crontab -e`), pointing at
the real PHP binary and the app's actual path:

```cron
0 * * * * /usr/bin/php /var/www/kymera-collection/bin/send-abandoned-cart-emails.php >> /var/www/kymera-collection/storage/logs/cron.log 2>&1
```

The script also writes its own summary line to
`storage/logs/{date}.log` via the app's normal `Logger` on every run
(`Abandoned cart reminders: N eligible, M sent, ...`), independent of
where cron redirects stdout - check that file first if a run seems to
have done nothing. Each script exits non-zero only on an uncaught
exception (e.g. the database is unreachable); a normal run with zero
eligible carts exits 0 and logs "0 eligible, 0 sent".

## 11. Post-deploy checklist

- [ ] `.env` has `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS is enforced (redirect HTTP → HTTPS at the web server or load balancer)
- [ ] Database imported/migrated, admin login works (`/admin/login`)
- [ ] `storage/logs`, `storage/cache`, `storage/sessions`, `public/uploads/*` are writable by the web server user
- [ ] At least one payment gateway is configured and test-purchased end-to-end, or COD is intentionally the only option
- [ ] A real email (not just the log file) was sent and received successfully, if `MAIL_HOST` is set
- [ ] `composer test` is green
- [ ] `bin/send-abandoned-cart-emails.php` is wired into the system crontab (section 10) - this app has no built-in scheduler daemon, so it silently never runs otherwise
