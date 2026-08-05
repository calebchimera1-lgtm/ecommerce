# Module 12 — Reports & Exports (PDF/Excel/CSV), Expense CRUD

## 1. Explanation

Adds four admin reports (Sales, Inventory, Customer, Product
Performance), each exportable to CSV, Excel, and PDF, plus the full
Expense CRUD that Module 9's dashboard deferred (expenses had been
read-only demo data since then).

### Export formats: one real dependency, two dependency-free

- **CSV** - native `fputcsv()`, no library.
- **Excel** - an HTML table served with the `application/vnd.ms-excel`
  MIME type and a `.xls` filename. Excel, LibreOffice Calc, and
  Numbers all open this directly as a real spreadsheet (correct
  columns, a title row) - it is not a renamed CSV. This project has
  favored hand-rolled code over extra packages throughout (custom MVC,
  no JS build step); pulling in PhpSpreadsheet (6 additional Composer
  packages) purely for flat report tables with no formulas, pivot
  tables, or multi-sheet workbooks would be dependency weight the
  actual requirement doesn't need.
- **PDF** - this one *does* need a real library; there's no honest way
  to hand-roll PDF generation. Added `dompdf/dompdf` (verified it
  resolves and installs cleanly via Composer in this environment,
  `composer.json` updated). `PdfExporter` renders a styled HTML
  summary + table to a landscape A4 PDF.

All three share the same calling convention -
`{Csv,Excel,Pdf}Exporter::stream($filename, ..., $headers, $rows)` -
so `Admin\ReportController` builds one set of header/row arrays per
report and hands them to whichever exporter the `?format=` query
parameter names, rather than duplicating data-shaping logic three
times per report.

### Bug found and fixed: PHP 8.4 deprecation notice corrupting CSV downloads

Testing the CSV export live (not just linting it) immediately showed
a genuine defect: PHP 8.4 deprecates calling `fputcsv()` without its
`$escape` parameter, and with `display_errors` on (this project's dev
`.env` has `APP_DEBUG=true`), that deprecation notice printed straight
into the response body *ahead of* the actual CSV content - so the
downloaded "CSV" started with an HTML `<br />` deprecation banner
before a single row of real data, which would fail to parse as CSV in
Excel or any script reading it. Confirmed via `file <download>` -
before the fix it reported plain Unicode text, not CSV; after passing
`escape: '\\'` explicitly to both `fputcsv()` calls, `file` correctly
identified the output as "CSV Unicode text, UTF-8 (with BOM)". This
would only be silently hidden in production (`APP_DEBUG=false` turns
off `display_errors`), which is exactly the kind of environment-dependent
bug that's easy to miss without actually downloading and inspecting
the file rather than just checking the HTTP status code.

### Expenses: a new permission, not an overloaded old one

Full expense entry (`Admin\ExpenseController`) is a materially
different trust level than *viewing* reports (`reports.view`, which
already existed) - anyone who can enter/edit/delete a financial
expense record can distort the dashboard's Profit figure, which is a
different risk than someone who can merely look at report numbers.
Rather than overload `reports.view` for both, this module adds a
dedicated `expenses.manage` permission via
`database/migrations/0003_add_expenses_permission.sql` (and directly
in `database/kymera_collection.sql`'s seed data for fresh installs).
Grant policy matches how Super Admin and Manager were already defined
in Module 1's seed rather than inventing a new rule: Super Admin's
grant is `SELECT 1, id FROM permissions` (literally every permission,
automatically including this new one), and Manager's grant is
"every permission except users/roles/settings/audit_logs" (also
automatically including it, since `expenses.manage` isn't in that
exclusion list) - so both the main schema file's seed and the
migration only needed to add the *permission row itself*, not touch
either role's grant logic. Verified live: Support (whose grant is an
explicit allow-list, not a NOT-IN exclusion) correctly gets 403 on
`/admin/expenses` without any changes to Support's own seed data.

### Reports read real data, not synthetic aggregates

Every report was verified against genuine order/product/customer rows
created through the real storefront and admin UI in earlier modules'
testing (not hand-inserted), confirmed exactly correct:

- **Sales Report**: a single real $241.98 paid COD order showed as
  Orders=1, Sales=$241.98, Revenue=$241.98 (Sales and Revenue match
  here specifically because that order is `payment_status = 'paid'` -
  the same Sales-vs-Revenue distinction Module 9 established holds in
  this report too), with every other day in range correctly zero-filled
  rather than just omitted.
- **Customer Report**: correctly attributed that same order to the
  purchasing customer with the right order count and total spent.
- **Product Performance Report**: correctly showed the product's
  `subtotal` (line total before shipping/tax), not the order's grand
  total - the report answers "how much did this product sell for,"
  which shipping/tax isn't part of.
- **Inventory Report**: correctly computed stock value as
  `stock_quantity * cost_price` for a real product (showed $0.00
  because that test product was created without a `cost_price` set -
  correct given the input, not a bug).

### Date range guards

`ReportController::dateRange()` defaults to the current calendar
month, silently swaps `start_date`/`end_date` if submitted backwards,
and clamps any range over 366 days back down to 366 days from the
start - verified live with both a reversed-date request and a
26-year-wide request, confirming the form re-renders with corrected,
sane dates rather than running an unbounded query or erroring.

## 2. Folder location / files delivered

```
app/Controllers/Admin/ReportController.php
app/Controllers/Admin/ExpenseController.php
app/Services/Export/CsvExporter.php
app/Services/Export/ExcelExporter.php
app/Services/Export/PdfExporter.php
app/Views/admin/reports/{index,sales,inventory,customers,products,_export-buttons}.php
app/Views/admin/expenses/{index,form}.php
database/migrations/0003_add_expenses_permission.sql
```

Updated: `composer.json` (added `dompdf/dompdf`), `app/Models/Expense.php`
(full CRUD support: `paginateAll()`, `countAll()`, `sumFiltered()`,
`categories()`), `app/Models/Order.php` (`salesSummary()`,
`salesReportDaily()`), `app/Models/OrderItem.php` (`bestSellingBetween()`),
`app/Models/User.php` (`topCustomers()`), `app/Models/Product.php`
(`inventoryReportRows()`; also fixed a pre-existing Module 11 doc-comment
misplacement on `adjustStock()`/`forSelect()` found while adding this
method - a cosmetic leftover from an earlier edit, not a functional
bug), `app/Views/admin/layouts/app.php` (sidebar entries for Reports,
Expenses), `routes/admin.php`, `database/kymera_collection.sql`
(`expenses.manage` permission seed).

## 3. Routes

```
GET  /admin/reports                      reports.view
GET  /admin/reports/sales[?format=]      reports.view
GET  /admin/reports/inventory[?format=]  reports.view
GET  /admin/reports/customers[?format=]  reports.view
GET  /admin/reports/products[?format=]   reports.view

GET  /admin/expenses                     expenses.manage
GET  /admin/expenses/create              expenses.manage
POST /admin/expenses                     expenses.manage
GET  /admin/expenses/{id}/edit           expenses.manage
POST /admin/expenses/{id}                expenses.manage
POST /admin/expenses/{id}/delete         expenses.manage
```

Each report route serves either the HTML report page or a file
download from the same URL, distinguished by an optional
`?format=csv|excel|pdf` query parameter - not four separate routes per
report.

## 4. SQL

`database/migrations/0003_add_expenses_permission.sql` - adds the
`expenses.manage` permission row and grants it to Super Admin and
Manager. No table schema changes; `expenses` has existed since
Module 1.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance and real generated
files (not just HTTP 200s):

1. Applied migration 0003, confirmed Super Admin and Manager both
   received `expenses.manage` via a direct query against
   `role_permissions`.
2. **Expense CRUD**: created a real expense through the admin form,
   confirmed it appeared on the Module 9 dashboard's Monthly Expenses
   tile with the exact amount; edited the amount and confirmed the
   tile updated; deleted it and confirmed the tile returned to $0.00.
   Confirmed audit log entries for create/update captured correct
   before/after values.
3. **Sales report**: confirmed the summary tiles and daily table
   exactly matched a real order in the database (see above). Exported
   CSV, Excel, and PDF - opened/inspected all three directly (not just
   checked status codes): CSV parsed cleanly with `file` after the fix
   below, Excel's HTML table content verified byte-for-byte, PDF's
   internal content stream was decompressed and confirmed to contain
   the correct title, date range, and summary line.
4. **Inventory, Customer, and Product Performance reports**: same
   pattern - verified HTML view numbers against direct DB queries,
   then verified all three export formats' actual content matched.
5. **RBAC**: confirmed Support (no `reports.view` grant needed here
   since Support's explicit allow-list never included it, and no
   `expenses.manage`) gets 403 on both `/admin/reports` and
   `/admin/expenses`; confirmed Manager (which gets both permissions
   automatically per Module 1's NOT-IN grant rule) gets full access
   with both sidebar links visible.
6. Confirmed a nonexistent expense id 404s and an invalid `?format=`
   value 400s rather than falling through silently.
7. Confirmed the date-range guards (swap-if-reversed, clamp-if-over-366-days)
   both correctly self-heal the query params shown back in the date
   inputs, rather than erroring or running an unbounded query.

## 6. Bugs found while testing (and fixed before commit)

- **PHP 8.4 `fputcsv()` deprecation notice corrupting CSV downloads**
  (detailed above): fixed by explicitly passing `escape: '\\'` to both
  `fputcsv()` calls in `CsvExporter`.
- **Pre-existing cosmetic doc-comment misplacement in `Product.php`**
  from Module 11 (`adjustStock()`'s docblock was orphaned above
  `forSelect()` due to an earlier edit's insertion point): noticed
  while adding `inventoryReportRows()` to the same file and fixed in
  passing. Not a functional bug - both methods worked correctly either
  way - but left as found would have misattributed the wrong doc
  comment to the wrong method for any future reader.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| Downloaded "CSV" file won't open / shows HTML text at the top | Old code before this module's fix, or a PHP version still emitting the `fputcsv()` deprecation with `display_errors` on | Upgrade to the fixed `CsvExporter` (passes `escape:` explicitly); as a general rule, never rely on `APP_DEBUG=false` alone to hide notices from a file download - fix the notice at the source |
| PDF export is blank or fails | `dompdf/dompdf` not installed (`composer install` wasn't run after pulling this module) | Run `composer install` - `dompdf/dompdf` is now a real `composer.json` dependency, not optional |
| Excel export opens as a text file instead of a spreadsheet | The `.xls` extension was stripped or the file was renamed - some mail clients/browsers do this to attachments with unfamiliar MIME handling | Save the file with its original `.xls` extension before opening in Excel/LibreOffice |
| Report shows $0.00 everywhere despite having orders | Orders exist but fall outside the selected date range (default is the current calendar month) | Widen the date range with the report's own start/end date filters |
| Inventory report's Stock Value column is always $0.00 for a product | That product has no `cost_price` set - Module 4's product form has always had this as an optional field | Set a cost price on the product's edit page if valuation should include it |

## 8. Next module

**Module 13 — Blog, testimonials, static pages, SEO, sitemap**: the
`blog_categories`/`blog_posts` tables have existed since Module 1 with
no admin UI or public-facing pages yet; `blog.manage` permission is
already seeded and unused. Testimonials have existed as customer-facing
read-only content since Module 5 with no admin moderation UI. Waiting
for confirmation to proceed.
