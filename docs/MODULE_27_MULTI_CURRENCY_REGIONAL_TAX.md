# Module 27 — Multi-Currency Display & Regional (State-Level) Tax Rates

## 1. Explanation

Two independent gaps flagged when this module was scoped: `TaxRate`
was a flat single-rate-per-country model even though the schema's
`tax_rates.state` column has existed since Module 1 unused, and every
price in the app - storefront, cart, checkout, admin, vendor payouts -
was hardcoded to USD with no notion of a shopper's own currency.

### Scope boundary: display currency, not transactional currency

The important decision this module makes is what it *doesn't* touch:
`orders.total`, `orders.currency` (still always written as `'USD'`
by `OrderPlacementService`), `vendor_orders.payout_amount`,
`return_requests.refunded_amount`, the Stripe gateway's `'currency' =>
'usd'` - none of it changes. Every dollar figure that represents real
money changing hands is still computed, stored, charged, and refunded
in USD, exactly as before this module. What's new is purely a
*display* layer: a shopper can browse the storefront with prices shown
in KES, GBP, or EUR instead of USD, using a manually-maintained
exchange rate (`currencies.exchange_rate`, units of that currency per
1 USD) - not a live FX API call. That's a deliberate scope decision,
not a shortcut: actually charging a card in a non-USD currency touches
payment gateway integration, multi-currency refund math (Module 25's
RMA already has carefully-scoped USD-only refund logic), and vendor
payout accounting - real financial-correctness risk for a "nice to
browse in your own currency" feature that doesn't need any of that to
deliver its value.

`money()` (the existing USD-formatting helper, unchanged, still used
everywhere by admin dashboards, vendor dashboards, order history,
invoices, checkout, and RMA refund screens) and the new
`displayPrice()` (storefront browsing only - product listings and the
product detail page) are deliberately two different functions, not one
function with a mode flag. `money()` is called in 31 view files
across `admin/`, `vendor/`, and the authoritative parts of `customer/`
(checkout, order history, invoices, returns) - making a currency
switch a shopper made while browsing bleed into an admin's revenue
report or a vendor's payout figure just because they happen to share a
browser session would be a real, dangerous correctness bug, not a
cosmetic one. Keeping them separate means that risk doesn't exist:
`displayPrice()` is only ever called from the two files that should
show a converted estimate (`app/Views/partials/product-card.php`,
`app/Views/customer/product/show.php`).

### How the display currency is chosen and stored

`Session::set('currency', $code)` / `Session::get('currency')` - the
same per-visitor session storage already used elsewhere in this app
(flash messages, the guest cart's session-scoped identity). A new
`GET /currency/{code}` route (`Customer\CurrencyController::set()`)
validates the code against `currencies.is_active = 1` before storing
it and redirects back via the existing `Controller::back()` helper.
This is a GET route, not a POST - a currency switch is a pure display
preference with no side effect on any protected resource, the same
category as a language or theme switcher, and this app's own CSRF
policy (audited exhaustively in Module 24) only ever requires a token
on routes that mutate real data. `currentCurrency()` (new helper in
`app/Helpers/functions.php`, memoized per-request with a `static`
local) resolves the session value back to a `currencies` row, falling
back to whichever row has `is_default = 1` for a visitor who's never
chosen one.

### State-level tax: `TaxRate::forAddress()` replaces `forCountry()`

`tax_rates.state` existed since the very first schema but
`TaxRate::forCountry()` never read it - every US shopper got the same
flat 8% regardless of state, which is wrong for real US sales tax
(California and New York, the two states this module seeds example
rates for, are both meaningfully different from the old blanket rate).
`forAddress(string $country, ?string $state = null)` now:

1. Prefers an exact `country` + `state` match (state name compared
   case-/whitespace-insensitively via `LOWER(TRIM(...))` on both
   sides, since checkout collects state as free text - see the "known
   limitation" note below).
2. Falls back to the country-level row (`state IS NULL`) if no
   state-specific row matches - including when no `$state` was given
   at all, or when the given state has no dedicated row (e.g. Texas
   still gets the flat "US Standard Sales Tax" rate).
3. Falls back to `defaultRate()` (the store's first active rate) if
   the country itself has no configured rate - unchanged from before.

`CheckoutController::store()` now passes `$shippingAddress['state']`
(already collected by the existing checkout form, just never used for
tax before) alongside the country. Verified live: a California billing
address on a $249.00 cart produced `tax_amount = 21.79` (249 × 8.75%),
not the old flat-rate `19.92` (249 × 8%) a Texas or generic-US address
still correctly gets.

**Known limitation, stated rather than hidden**: state matching is an
exact (normalized) string comparison, not abbreviation-aware - a
shopper who types "CA" instead of "California" will not match the
California-specific rate and will silently fall back to the
country-level one. Fixing this properly means either constraining the
checkout form to a fixed state dropdown (a real UX/schema change
beyond this module's scope) or building an abbreviation-normalization
table - deferred, not attempted here.

## 2. Files

- `database/migrations/0011_add_multi_currency_and_state_tax.sql` —
  new `currencies` table + seed rows (USD/KES/GBP/EUR); two new
  state-level `tax_rates` rows (California, New York).
- `database/kymera_collection.sql` — same, for fresh installs.
- `app/Models/Currency.php` — new model (`active()`, `defaultCurrency()`).
- `app/Models/TaxRate.php` — `forCountry()` renamed to `forAddress()`,
  now state-aware.
- `app/Controllers/Customer/CheckoutController.php` — passes the
  shipping state into `TaxRate::forAddress()`.
- `app/Controllers/Customer/CurrencyController.php` — new, handles
  `GET /currency/{code}`.
- `routes/web.php` — the new currency route.
- `app/Helpers/functions.php` — new `currentCurrency()` and
  `displayPrice()` helpers.
- `app/Views/customer/layouts/site.php` — currency dropdown in the
  main nav.
- `app/Views/partials/product-card.php`, `app/Views/customer/product/show.php` —
  switched from `money()` to `displayPrice()`; the product detail page
  also shows a "you'll be charged in USD at checkout" note when a
  non-USD currency is selected.
- `tests/Feature/Models/TaxRateTest.php`, `tests/Feature/Models/CurrencyTest.php` —
  16 new tests total.

## 3. How to test

1. Visit any page - the currency dropdown in the top nav lists every
   active currency. Pick one; you're redirected back to the same page
   with storefront prices (shop grid, product detail) converted.
2. Cart, checkout, order history, and invoices stay in USD regardless
   of the selected display currency - by design (section 1).
3. At checkout, enter a US billing/shipping address with `California`
   or `New York` as the state and confirm the resulting order's
   `tax_amount` reflects that state's rate rather than the flat 8%.

## 4. Live verification performed

- Confirmed `displayPrice()` produces byte-identical output to the
  old `money()` calls when USD (the default) is selected - zero visual
  regression for a shopper who never touches the currency switcher.
- Switched to KES via the real `/currency/KES` route and confirmed
  the product detail page, shop grid, and the "Estimated in KES..."
  disclaimer all showed correctly converted amounts (`$249.00` →
  `KSh 32,245.50`, exactly `249 × 129.5`).
- Confirmed the cart page stays in USD even with a non-USD currency
  selected in the same session - the authoritative/display split
  actually holds, not just in theory.
- Switched back to USD and confirmed prices matched the original
  `money()` output exactly.
- Placed a real order through `/checkout` with a California billing
  address (`same_as_shipping` checked) and confirmed
  `orders.tax_amount = 21.79` on a `249.00` subtotal - the California
  8.75% rate, not the old flat 8%.
- Verified `TaxRate::forAddress()` against five scenarios directly:
  exact state match, case-insensitive state match, unmatched state
  (falls back to country), no state given (falls back to country), and
  an unconfigured country (falls back to the global default) - all
  correct.
- Ran the full PHPUnit suite three times in a row (83 tests, up from
  73, identical results each run), `composer lint`, and `composer cs`
  (0 errors/warnings).

## 5. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| A shopper's cart/checkout total doesn't match the currency they were browsing in | Intentional (section 1) - only storefront browsing pages convert for display; checkout is always authoritative USD | Not a bug: if real multi-currency checkout is ever needed, that's a much larger follow-up (payment gateway currency support, multi-currency refund/payout accounting), not something to bolt onto this module |
| A US state's tax rate looks wrong even though a `tax_rates` row exists for it | The state was typed differently than the seeded row (e.g. an abbreviation, or a typo) - matching is an exact normalized-string comparison, not abbreviation-aware (section 1's known limitation) | Check the customer's exact `shipping_state`/`billing_state` input against `tax_rates.state` for that country; add a matching row or normalize the input if this becomes a recurring issue |
| Prices shown to an admin or vendor look converted to a currency they didn't expect | Would only happen if an admin/vendor view were changed to call `displayPrice()` instead of `money()` - by design, none of them do | Never call `displayPrice()` outside the two storefront browsing views listed in section 2; every other money figure in this app must keep calling `money()` directly |

## 6. Next module

**Module 28 — Vendor tiers / Top Rated Seller badges**: builds on
Module 21's real vendor rating data (customers rate vendors, not just
products) to classify vendors into tiers and surface a "Top Rated
Seller" badge on their storefront and product listings.
