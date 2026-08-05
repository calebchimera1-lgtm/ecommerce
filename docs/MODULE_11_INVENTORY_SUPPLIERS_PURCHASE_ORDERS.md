# Module 11 — Inventory, Suppliers, Purchase Orders

## 1. Explanation

Gives the `suppliers`, `purchase_orders`, and `purchase_order_items`
tables (schema since Module 1, unused until now) a real admin UI, adds
a stock overview + manual adjustment screen, and finally lets products
be linked to the supplier they're restocked from - a field Module 4's
product form never exposed.

### Making `partially_received` actually reachable

`purchase_orders.status` has included `partially_received` since
Module 1's schema, but `purchase_order_items` had no column to track
how much of each *line* had arrived - only an order-level status with
no line-level backing data, which makes partial receiving impossible
to implement correctly (there'd be no way to know what's still owed
on a second, later receipt). Rather than fake partial status with no
real tracking, this module adds `purchase_order_items.received_quantity`
via `database/migrations/0002_add_purchase_order_received_quantity.sql`
(and directly in `database/kymera_collection.sql` for fresh installs) -
the same "migration + update main schema" pattern Module 6 and Module 9
used for their own schema additions.

### Receiving is transactional and server-authoritative

`PurchaseOrder::receiveItems()` takes a `[item_id => quantity now]` map
and, in one transaction: caps each line's applied quantity at what's
still outstanding (`ordered - already_received`), adds that delta to
`products.stock_quantity`, writes a matching `inventory_movements` row
(`type = 'in'`, `reference_type = 'purchase_order'`), and recomputes
the PO's overall status from its items (`partially_received` while any
line is incomplete, `received` with `received_at` set once every line
is fully in).

The cap is enforced server-side regardless of what the form sends -
verified live by submitting a receive request for **99** units against
a line with only 8 remaining: the item's `received_quantity` stopped
at its ordered quantity (20 of 20), stock only increased by the 8
actually owed (not 99), and the PO status correctly flipped to
`received`. The HTML `max` attribute on the receive form's quantity
inputs is a UX hint, not the actual guard.

**Bug found and fixed during this same test**: the audit log entry for
that receive action originally recorded the raw submitted quantities
(`{"2": 99}`) rather than what was actually applied. Since audit log
entries exist to answer "what really changed," logging an unapplied,
over-the-limit number a user typed into a form is misleading - a
reviewer reading that entry later would wrongly conclude 99 units were
received. Fixed by having `PurchaseOrder::receiveItems()` return the
per-item deltas it actually applied, and having `PurchaseOrderController::receive()`
log that returned value instead of the raw request input. Re-tested
the same 99-unit-over-request scenario afterward and confirmed the
audit entry now reads `{"2": 5}` (the real applied amount, capped by
what remained on a 5-unit order line).

### Stock adjustments: type dictates sign, not the admin

`Admin\InventoryController::adjust()` takes a `type` (`in`, `out`,
`adjustment`, `damaged`, `return`) and a plain positive `quantity`, and
the *type* decides whether the delta is added or subtracted -
`in`/`return` always increase stock, `out`/`damaged` always decrease
it, and only `adjustment` (a stock-take correction) lets the number's
own sign through unchanged. This means an admin typing "damaged, 2"
can't accidentally *increase* stock by mistyping a sign - verified
live: adjusting with `type=damaged, quantity=2` (a positive number)
correctly *reduced* stock by 2 and wrote a matching
`inventory_movements` row with `quantity = -2`.

### Suppliers: deactivate-or-delete, same pattern as brands

`Admin\SupplierController::destroy()` attempts a real hard delete and
catches the `PDOException` a `purchase_orders.supplier_id ON DELETE
RESTRICT` constraint throws once that supplier has order history -
the same try/catch-and-flash pattern Module 4's `BrandController`
already established, rather than inventing a new deactivate-only
convention. `products.supplier_id` is `ON DELETE SET NULL`, so a
supplier with only product links (no PO history yet) deletes cleanly
and those products simply lose their supplier reference. Verified live:
deleting the supplier used by this module's own test purchase order
was correctly refused with a friendly message; the row was untouched.

### Product ↔ supplier link, finally wired up

Module 4's `ProductController::store()`/`update()` always hardcoded
`supplier_id => null` - there was no field on the form and no handling
in the controller. This module adds the dropdown (sourced from active
suppliers) and the controller-side validation (rejects a nonexistent
supplier id the same way it already does for category/brand). Verified
live: assigning a supplier to an existing product through the real
edit form, then confirming it appears correctly on the new Inventory
overview page's Supplier column.

## 2. Folder location / files delivered

```
app/Models/Supplier.php
app/Models/PurchaseOrder.php
app/Models/PurchaseOrderItem.php
app/Controllers/Admin/SupplierController.php
app/Controllers/Admin/PurchaseOrderController.php
app/Controllers/Admin/InventoryController.php
app/Views/admin/suppliers/{index,form}.php
app/Views/admin/purchase-orders/{index,create,show}.php
app/Views/admin/inventory/{index,movements}.php
public/assets/js/admin-po-form.js
database/migrations/0002_add_purchase_order_received_quantity.sql
```

Updated: `app/Models/Product.php` (`forSelect()`, `adjustStock()`,
`paginateInventory()`, `countInventory()`), `app/Models/InventoryMovement.php`
(`paginateAll()`, `countAll()`), `app/Controllers/Admin/ProductController.php`
(supplier_id handling in create/store/edit/update),
`app/Views/admin/products/form.php` (supplier dropdown),
`app/Views/admin/layouts/app.php` (sidebar entries for Inventory,
Purchase Orders, Suppliers), `routes/admin.php`,
`database/kymera_collection.sql` (`purchase_order_items.received_quantity`).

## 3. Routes

```
GET  /admin/suppliers                        suppliers.manage
GET  /admin/suppliers/create                 suppliers.manage
POST /admin/suppliers                        suppliers.manage
GET  /admin/suppliers/{id}/edit              suppliers.manage
POST /admin/suppliers/{id}                   suppliers.manage
POST /admin/suppliers/{id}/delete            suppliers.manage

GET  /admin/purchase-orders                  inventory.manage
GET  /admin/purchase-orders/create           inventory.manage
POST /admin/purchase-orders                  inventory.manage
GET  /admin/purchase-orders/{id}             inventory.manage
POST /admin/purchase-orders/{id}/order       inventory.manage
POST /admin/purchase-orders/{id}/receive     inventory.manage
POST /admin/purchase-orders/{id}/cancel      inventory.manage

GET  /admin/inventory                        inventory.manage
GET  /admin/inventory/movements              inventory.manage
POST /admin/inventory/{id}/adjust            inventory.manage
```

Purchase orders and stock adjustments are gated on `inventory.manage`
(not a separate permission) since both directly move stock - the
supplier directory itself is a separate concern gated on
`suppliers.manage`, matching the two distinct permission slugs already
seeded in Module 1.

## 4. SQL

`database/migrations/0002_add_purchase_order_received_quantity.sql` -
adds `purchase_order_items.received_quantity INT UNSIGNED NOT NULL
DEFAULT 0`. Applied to the schema file directly for fresh installs, and
via this migration for databases created before this module. No other
schema changes - `suppliers`, `purchase_orders`, and `purchase_order_items`
already existed as designed in Module 1.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance, driving every
action through the actual admin UI:

1. Applied the migration to the running dev database, confirmed the
   new column via `DESCRIBE purchase_order_items`.
2. Created a real supplier through the admin form, then edited the
   Module 10 test product to assign that supplier - confirmed via the
   Inventory overview page that the supplier name now shows against
   that product.
3. **Manual stock adjustment**: adjusted stock down with `type=damaged,
   quantity=2` (positive input) - confirmed stock actually *decreased*
   by 2, the `inventory_movements` row recorded a signed `-2`, and an
   audit log entry captured the before/after stock level. Confirmed
   the entry renders correctly on the new Movement Log page with the
   acting admin's name attached.
4. **Purchase order lifecycle**: created a draft PO for 20 units at
   $95/unit (confirmed `total_amount` computed as exactly $1,900.00),
   marked it Ordered, then received 12 of 20 - confirmed status became
   `partially_received`, stock rose by exactly 12, and `received_at`
   stayed `NULL`. Then submitted a receive request for 15 more (only 8
   remained) - confirmed the server capped the applied amount at 8
   (not 15), stock rose by exactly 8, status flipped to `received`,
   and `received_at` was set. Confirmed both partial receipts appear
   as two separate `in`-type `inventory_movements` rows referencing
   the PO.
5. Found and fixed the audit-log-shows-unapplied-quantity bug
   described above during step 4's over-request test; re-verified
   with a second PO that the fix produces an audit entry matching the
   real applied delta, not the raw form input.
6. Confirmed a `received` PO refuses cancellation (stock has already
   moved) with a flashed error, and that a nonexistent PO id 404s
   rather than erroring.
7. Confirmed a supplier with purchase order history can't be deleted
   (friendly error, row untouched), consistent with the FK's `ON
   DELETE RESTRICT`.
8. **RBAC**: confirmed a Support-role admin (no `inventory.manage` or
   `suppliers.manage`) gets 403 on all three new sections and doesn't
   see their sidebar links, while a Manager-role admin (which has
   both permissions per Module 1's seed) gets full access and sees all
   three links.
9. Confirmed the Module 9 dashboard's existing Low Stock Alerts widget
   picks up a manual stock adjustment made here without any code
   changes on the dashboard side - lowered a product's stock below its
   threshold via Inventory, reloaded the dashboard, confirmed it
   appeared; restored the stock level afterward.

## 6. Bugs found while testing (and fixed before commit)

- **Audit log recorded unapplied quantities on over-requested receives**
  (described in detail above): `PurchaseOrderController::receive()`
  logged the raw `receive_qty[]` values submitted by the form instead
  of what `PurchaseOrder::receiveItems()` actually applied after
  capping at the remaining owed quantity. Fixed by having
  `receiveItems()` return the applied deltas and logging those.

No other bugs found - the environment issues from Module 10 (DB host
resolution, SMTP timeout) were already fixed in the persisted `.env`
and didn't recur.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| `SQLSTATE[42S22]: Column not found: received_quantity` | Migration 0002 wasn't applied to an existing database created before this module | Run `database/migrations/0002_add_purchase_order_received_quantity.sql` against your database |
| Receiving stock does nothing / PO status never changes | All `receive_qty[]` values were 0 or blank, or the PO wasn't in `ordered`/`partially_received` status yet | Enter a quantity greater than 0 for at least one line; mark the PO as Ordered first if it's still a draft |
| A manual "damaged" or "out" adjustment increased stock instead of decreasing it | Shouldn't happen - the type, not the typed sign, controls direction. If it does, the `quantity` field was submitted as a raw negative number that got double-negated | Enter plain positive quantities; the type dropdown alone determines the sign applied |
| New product can't be assigned a supplier | The supplier is inactive (`is_active = 0`) - only active suppliers populate the dropdown, matching how the brand/category dropdowns already only offer usable options | Reactivate the supplier via `/admin/suppliers/{id}/edit`, or choose a different active supplier |
| Deleting a supplier fails | Supplier has purchase order history (`ON DELETE RESTRICT`) | Not deletable by design once it has PO history - set `is_active = 0` on the edit form instead of deleting |

## 8. Next module

**Module 12 — Reports & exports (PDF/Excel/CSV)**: sales, inventory,
and customer reports with downloadable exports, plus the full Expense
CRUD that Module 9 deferred (expenses have been read-only/seed-data
only since then). Waiting for confirmation to proceed.
