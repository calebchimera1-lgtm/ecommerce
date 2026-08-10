# Module 22 — Automated Test Suite

## 1. Explanation

Every one of the previous 21 modules was verified the same way: build
it, then live-test it against a real MariaDB instance through the
actual running app - curl against the dev server, direct SQL
assertions, repeated for every module's own test plan. That rigor is
real, but it's manual - nothing catches a regression introduced by a
later module touching earlier code (which happened repeatedly:
Module 17 changed `Product`'s public queries, Module 18 changed
`OrderPlacementService`, Module 19 changed those same public queries
again) except re-running curl sessions by hand. This module adds an
automated PHPUnit suite covering the logic where a silent regression
would matter most, runnable in seconds instead of a manual session.

`phpunit` and `squizlabs/php_codesniffer` were already declared in
`composer.json`'s `require-dev` since Module 1, with a `composer test`
script pointing at them - but no `phpunit.xml`, no `tests/` directory,
and no actual tests existed until this module. The scaffolding was
aspirational; this module is what fills it in.

### A dedicated test database, never the dev database

`tests/bootstrap.php` overrides `DB_DATABASE` to
`kymera_collection_test` via `putenv()` *before* anything else in the
app loads `.env` - `App\Core\Env::load()` only sets a variable if
`getenv()` doesn't already return one (see `app/Core/Env.php`), so
this override survives untouched through `config/database.php`'s own
later `Env::load()` call, which then only fills in the remaining
values (host/username/password) from the real `.env`. The bootstrap
refuses to run at all if the resolved database name doesn't end in
`_test`, as a hard stop against a misconfigured environment pointing
tests at real data.

If the test database doesn't exist, or exists with zero tables, the
bootstrap creates and seeds it from `database/kymera_collection.sql` -
the exact schema fresh installs use - via the `mysql` CLI client. That
file hardcodes `CREATE DATABASE IF NOT EXISTS kymera_collection` /
`USE kymera_collection` for fresh installs; importing it unmodified
would silently create and seed the **real dev database** regardless of
which database name is passed on the CLI, since the file's own `USE`
statement wins over the connection target. The bootstrap rewrites both
occurrences to the test database name before importing - caught while
first testing this module (see section 6).

### Two isolation strategies, because one doesn't fit everything

`Tests\TestCase` wraps every test in a transaction, rolled back in
`tearDown()` - fast, and the default for anything that's a
straightforward CRUD/query test against models. It does **not** work
for code that owns its own transaction: `OrderPlacementService::place()`
calls `$db->beginTransaction()`/`commit()` internally, and PDO/MySQL
don't support real nested transactions - a second `beginTransaction()`
call while one is already open throws. `Tests\IntegrationTestCase`
exists for exactly that case: instead of wrapping a transaction, it
truncates the order-pipeline tables (`orders`, `order_items`,
`vendor_orders`, `payments`, etc.) at the start of every test, so each
test starts from a clean slate without fighting the code under test
for control of the transaction.

`IntegrationTestCase` deliberately does **not** truncate fixture
tables (`users`, `vendors`, `categories`, `products`) - `Tests\Support\Factory`
generates a fresh random token for every unique field (email, slug),
so leftover fixture rows from a prior test run never collide with a
new one, and re-running the suite doesn't require wiping the test
database first (verified live - see section 5).

### What's covered, and what deliberately isn't

Given the size of this codebase (21 modules), this suite is not
exhaustive - it targets the logic where a silent bug would be worst:

- **Order splitting and commission math** (`OrderPlacementServiceTest`)
  - the highest-stakes logic in the app: real money, cross-vendor data
  isolation, and the exact computation Module 18 built. Covers
  platform-only carts (zero `vendor_orders` rows), mixed carts (split
  correctly, commission computed per line), category-specific vs.
  platform-default commission rates, multiple vendors in one cart each
  getting their own sub-order, stock decrementing, cart clearing, and
  that an insufficient-stock rejection leaves stock and the cart
  completely untouched (the whole placement really is atomic).
- **Public visibility gating** (`ProductTest`, `VendorTest`) - including
  a direct regression test for the bug Module 19 found and fixed (a
  suspended vendor's already-approved products must disappear from
  `findActiveBySlug()`, `publicPaginate()`, and the sitemap the moment
  the vendor is suspended, and reappear the moment they're
  reactivated) - the kind of behavior a manual curl session verifies
  once and an automated test verifies on every future change.
- **Cross-entity ownership scoping** (`Product::findForVendor()`,
  `VendorOrder::findForVendor()`) - the pattern every vendor-portal
  controller since Module 17 relies on to stop one vendor reaching
  another's data by guessing an id.
- **Pure logic**: `Str::slug()`'s transliteration/collapsing rules,
  `Validator`'s rule set (including the numeric-vs-string-length
  min/max distinction and the `unique` rule's ignore-id behavior for
  edit forms), `CartCalculator`'s discount/free-shipping-threshold/tax
  math (including that the free-shipping threshold is checked
  *after* a coupon discount is applied, not against the raw subtotal).

**Not covered by this suite**: full HTTP-level request/response
testing (this hand-rolled `Router`/`Controller` stack has no test
client or kernel abstraction to dispatch a request against without a
running server - that's what every module's manual curl-based live
testing has already exhaustively exercised for its own module), view
rendering, email content, file uploads (`ImageUploader`'s MIME/size
validation would need real temp files with faked `is_uploaded_file()`
behavior PHPUnit doesn't provide out of the box), and payment gateway
integrations (no real credentials exist to test against - see the
Production Readiness section of the README).

## 2. Folder location / files delivered

```
tests/bootstrap.php
tests/TestCase.php
tests/IntegrationTestCase.php
tests/Support/Factory.php
tests/Unit/Core/StrTest.php
tests/Unit/Core/ValidatorTest.php
tests/Unit/Services/CartCalculatorTest.php
tests/Feature/Models/ProductTest.php
tests/Feature/Models/VendorTest.php
tests/Feature/Models/VendorOrderTest.php
tests/Feature/Services/OrderPlacementServiceTest.php
phpunit.xml
```

Updated: `composer.json` (`autoload-dev` PSR-4 mapping for `Tests\\`),
`.gitignore` (`.phpunit.cache/`, `.phpunit.result.cache`).

## 3. Routes

None - this module adds no application routes.

## 4. SQL

None - tests run against the existing `database/kymera_collection.sql`
schema, imported into a separate `kymera_collection_test` database by
the bootstrap.

## 5. Testing instructions

Verified by actually running the suite repeatedly, not just reading
it:

```
composer test
# or directly:
vendor/bin/phpunit
```

1. Ran `vendor/bin/phpunit --testsuite Unit` first, in isolation, to
   validate the pure/lightweight tests before building out the
   heavier feature tests - caught one genuine test-expectation bug
   immediately (see section 6).
2. Ran each new `Feature` test file individually as it was written
   (`ProductTest`, `VendorTest`, `VendorOrderTest`,
   `OrderPlacementServiceTest`), confirming each passed on its own
   before combining them.
3. Ran the full suite together (`vendor/bin/phpunit`, no filters) -
   all 57 tests, 128 assertions passed.
4. **Idempotency check**: ran the full suite a second and third time
   immediately after, with no manual cleanup in between - confirmed
   identical results each time. This directly exercises the
   cross-process fixture-collision bug described in section 6; before
   the fix, this exact repeated-run check is what surfaced it.
5. Confirmed `composer test` (the wrapper script) produces the same
   result as calling `vendor/bin/phpunit` directly.
6. Confirmed the bootstrap correctly refuses to run against anything
   other than a database ending in `_test` (read the guard in
   `tests/bootstrap.php` directly - this wasn't given a failing
   integration test since it would require deliberately misconfiguring
   `.env`, which isn't something to exercise against a real
   environment).

## 6. Bugs found while testing (and fixed before commit)

Two real bugs, both in this module's own new code (not the
application code the tests exercise):

1. **Schema import would have silently seeded the wrong database.**
   The first version of `tests/bootstrap.php` imported
   `database/kymera_collection.sql` unmodified via the `mysql` CLI,
   passing the test database name as the connection target. The
   schema file itself contains `CREATE DATABASE IF NOT EXISTS
   kymera_collection` / `USE kymera_collection` near the top - those
   statements override whatever database the CLI connected to, so the
   import would have silently created/reseeded the **real dev
   database** instead of the test one. Caught by inspecting the schema
   file's header before trusting the naive import approach, before it
   ever ran against anything. Fixed by rewriting both occurrences of
   `` `kymera_collection` `` to the test database name in memory before
   writing to a temp file and importing that instead.
2. **Fixture uniqueness collided across separate test runs.**
   `Tests\Support\Factory`'s first version generated unique
   emails/slugs from a simple incrementing counter
   (`customer1@example.test`, `customer2@example.test`, ...). That
   counter is a `static` property, private to one PHP process - every
   fresh `vendor/bin/phpunit` invocation restarts it at zero.
   `Tests\IntegrationTestCase` deliberately never truncates fixture
   tables (by design - see section 1), so rows created by an earlier
   process's `OrderPlacementServiceTest` run stayed permanently
   committed in the test database. Running the full suite fresh
   afterward hit a `Duplicate entry 'customer1@example.test'` error
   the moment `ValidatorTest`'s `unique`-rule test (the first test in
   that run to call `Factory::customer()`) tried to insert the same
   email a leftover row from the earlier run already held. Fixed by
   generating every unique field from a random token
   (`bin2hex(random_bytes(4))`) instead of a bare counter, so
   collisions across separate process runs are no longer possible
   regardless of what's already committed in the test database.
   Verified fixed via the repeated full-suite runs in section 5.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `Refusing to run tests against a database not ending in '_test'` | `DB_DATABASE` in the environment already resolves to something not ending in `_test` before the bootstrap's `putenv()` call can win | This shouldn't happen through normal use (the bootstrap sets its own override first) - if it does, check for an environment variable set outside `.env` (e.g. by CI) overriding `DB_DATABASE` |
| `Failed to seed test database` with a `mysql` command error in the output | The `mysql` CLI client isn't installed, or the configured `DB_USERNAME`/`DB_PASSWORD` in `.env` can't authenticate | Confirm `mysql --version` works from the shell and that `mysql -u<user> -p<password>` connects manually with the same credentials `.env` has |
| A test using `Tests\TestCase` fails with `There is already an active transaction` | The code under test calls `$db->beginTransaction()` itself (e.g. `OrderPlacementService`) while `TestCase`'s own outer transaction is still open | Extend `Tests\IntegrationTestCase` instead of `Tests\TestCase` for that test class |
| Running the suite twice in a row produces a duplicate-entry error | A test class extends `IntegrationTestCase`, calls `Tests\Support\Factory`, but a *literal* (non-Factory) unique value was hardcoded in the test itself | Use `Factory`'s generated values instead of a literal string for anything that needs to be unique in a table `IntegrationTestCase` doesn't truncate |
| `composer test` fails immediately with a Composer superuser warning, but `vendor/bin/phpunit` directly works fine | Composer refuses to run scripts as root without an explicit opt-in (sandbox-only situation, not an app or test issue) | Run with `COMPOSER_ALLOW_SUPERUSER=1 composer test`, or just call `vendor/bin/phpunit` directly |

## 8. Next module

**Module 23 — CI pipeline + deployment docs**: a GitHub Actions
workflow running `php -l`, this test suite, and PHP_CodeSniffer on
every push/PR, plus a `DEPLOYMENT.md` covering server requirements,
`.env` setup, running migrations, and exactly which payment/SMTP
credentials are needed and where they go.
