# Module 23 — CI Pipeline & Deployment Docs

## 1. Explanation

Module 22 gave the project a test suite; this module makes sure it
actually runs on every change instead of relying on someone
remembering to run `composer test` locally, and writes down everything
a real deployment needs that isn't already obvious from the code.

### CI runs three checks, only two of which are allowed to fail the build

`.github/workflows/ci.yml` spins up a real MySQL 8 service container
(GitHub Actions' built-in service-container support, not a mocked
database) and runs, in order:

1. **`composer lint`** (new script - `php -l` across
   `app`/`public`/`routes`/`config`) - catches a syntax error before
   anything else even tries to run.
2. **`composer test`** - the full Module 22 suite, against a real
   database exactly like local development, using the same
   `tests/bootstrap.php` isolation.
3. **`composer cs`** - PHP_CodeSniffer against PSR-12, but scoped and
   configured to actually fail the build on errors (see below), rather
   than being cosmetic.

Lint and test failures fail the build outright, as they should -
either means genuinely broken code. Code style is real too, but scoped
differently: see the next section for why.

### Code style was declared in Module 1 but never actually enforced

`composer.json` has had a `cs` script (`phpcs --standard=PSR12 app/`)
since Module 1, but nothing ever ran it in anger. Running it for the
first time against the full `app/` tree found 505 errors and 644
warnings across 136 files - not because the code is bad, but because
PSR-12 (written for plain PHP class files) doesn't fit `app/Views/*`,
which deliberately mixes HTML and PHP the way every template in this
project has since Module 5. Line-length and file-docblock rules that
make sense for a `Controller` class are just noise against a `.php`
file that's 80% HTML.

Rather than either ignore the whole check (declared-but-meaningless,
same problem as before this module) or mechanically "fix" 500+
findings across view files in a way that would touch nearly every
template in the app for no functional benefit, this module scopes
`phpcs` to app logic only - `Controllers`, `Core`, `Middleware`,
`Models`, `Services` - via a new `phpcs.xml.dist`. Against that scope,
the real result is **0 errors, 77 warnings** (mostly line length) -
warnings are reported but don't fail the build
(`--warning-severity=0`), errors do. That's a check worth having
without asking future work to satisfy a standard views were never
meant to.

### Deployment docs describe what exists, not what's aspirational

`DEPLOYMENT.md` was written by working through an actual deploy
checklist against this specific codebase - server requirements read
directly off `composer.json`'s `require` block and this module's own
CI workflow's extension list; the migration-application instructions
were checked against what the seven existing migration files actually
do (none use `IF NOT EXISTS` - confirmed by grepping all of them
before writing that section, see section 6 for the claim this caught);
the payment-gateway table was checked against
`PaymentGatewayManager::isConfigured()` and the checkout view's actual
disabled-not-hidden behavior for unconfigured gateways, not assumed
from the `.env.example` comments alone.

## 2. Folder location / files delivered

```
.github/workflows/ci.yml
phpcs.xml.dist
DEPLOYMENT.md
```

Updated: `composer.json` (new `lint` script; `cs` script now just
invokes `phpcs`, which auto-discovers `phpcs.xml.dist`).

## 3. Routes

None - this module adds no application routes.

## 4. SQL

None.

## 5. Testing instructions

Verified by actually running the pipeline's steps, not just writing
the YAML and assuming it works:

1. Ran `vendor/bin/phpcs --standard=PSR12 app/` (the original,
   unscoped Module-1 configuration) to get a real baseline: 505 errors
   / 644 warnings / 136 files. Then ran it scoped to app logic only
   (`app/Core app/Models app/Services app/Middleware app/Controllers`,
   excluding `app/Views`): 0 errors / 77 warnings / 37 files - the
   number that justified excluding Views rather than assuming it.
2. Confirmed `phpcs`'s default exit code is non-zero on warnings too
   (not just errors) by running it without `--warning-severity=0`
   first and checking `$?` - confirmed `1`. Added
   `warning-severity="0"` to `phpcs.xml.dist` and re-ran - confirmed
   exit `0` with the same 0-errors/77-warnings result, so the CI step
   genuinely only fails on real errors.
3. **Validated the CI YAML syntax** with a Python `yaml.safe_load()`
   parse (no `yaml` PHP extension available in this environment) -
   confirmed it parses without error.
4. **Manually replicated every CI step locally against this project's
   own MariaDB instance** (not literally GitHub's MySQL 8 service
   container, but the same commands against a real server): generated
   a `.env` from `.env.example` the same way the workflow's "Create
   .env for CI" step does (`cp` + `sed` replacements, including a
   freshly generated `APP_KEY`), pointed it at a dropped-and-recreated
   `kymera_collection_test` database, then ran `composer lint`,
   `composer test`, and `composer cs` in the same order the workflow
   does. All three passed: lint clean across all files, 57/57 tests,
   0 phpcs errors. Confirmed the real dev database
   (`kymera_collection`) was untouched throughout (`SELECT COUNT(*)
   FROM vendors` before/after matched), and that the temporary `.env`
   swap was fully reverted afterward.
5. Confirmed `composer lint` (new script) actually runs `php -l`
   against every file under `app`/`public`/`routes`/`config` and exits
   `0` when everything is clean - not just that it doesn't crash.

## 6. Bugs found while testing (and fixed before commit)

One real bug, caught before it reached the deployment docs rather than
after: an early draft of `DEPLOYMENT.md`'s migration-application
section claimed the migration files "use `IF NOT EXISTS` guards" as a
reason they're safe to re-run. Grepping all seven files
(`grep -l "IF NOT EXISTS" database/migrations/*.sql`) found zero
matches - none of them are actually idempotent; each is a one-shot
`ALTER TABLE`/`CREATE TABLE` meant to run exactly once. Following that
false claim during a real deployment would have led someone to expect
a harmless no-op on re-run instead of the loud duplicate-column/table
error that's what actually happens. Corrected before committing to
describe the real, non-idempotent behavior and recommend tracking
which migrations have been applied.

A second, smaller inaccuracy was also caught before committing:
`DEPLOYMENT.md` initially said the checkout page "only offers a
gateway if it's configured" - checking `app/Views/customer/checkout/index.php`
directly showed every gateway is always rendered, just with
`disabled` set on the radio input when unconfigured (so a customer
sees "PayPal - not currently available" rather than PayPal not
existing on the page at all). Corrected to describe the actual
disabled-not-hidden behavior.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| CI fails at "Create .env for CI" or "Wait for MySQL" | The MySQL service container didn't come up in time, or its port mapping changed | Check the workflow run's service-container logs in the GitHub Actions UI; the health-check retry loop should cover normal startup delay, but a genuinely failed container needs investigating there, not in this repo's code |
| `composer cs` fails locally with far more than 77 warnings / any errors | Running plain `phpcs --standard=PSR12 app/` instead of just `phpcs` (which auto-loads `phpcs.xml.dist`'s scoped rules) | Always run `composer cs` (or bare `vendor/bin/phpcs` with no extra args) rather than passing your own `--standard`/path arguments that bypass the project's config |
| `composer lint`/`test`/`cs` fail locally with a Composer "superuser" error | Running as root without `COMPOSER_ALLOW_SUPERUSER=1` (sandbox/CI-root situation, not a real project issue) | Prefix the command with `COMPOSER_ALLOW_SUPERUSER=1`, or call `vendor/bin/phpunit` / `vendor/bin/phpcs` directly instead of through Composer |
| A migration fails with "duplicate column" or "duplicate table" during deployment | That migration was already applied - none of them are idempotent by design | Skip it and move to the next migration number; this is expected, not a sign of corruption |

## 8. Next module

**Module 24 — Security review pass**: audit CSRF coverage, auth/
permission gating, SQL injection surface, file upload validation, and
session handling across all 21 feature modules; document findings and
fix anything real. With CI now running the test suite on every push,
any fix this review makes gets the same automated regression coverage
for free.
