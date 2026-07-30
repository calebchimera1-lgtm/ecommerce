# Module 4 — Product Catalog (Admin CRUD)

## 1. Explanation

Admin-side management for **categories**, **brands**, and **products**
(with multiple images, colour/size variants, and free-form
specifications), gated behind the `categories.manage`, `brands.manage`,
and `products.manage` permissions from Module 1's RBAC matrix via the
`PermissionMiddleware` infrastructure from Module 3. Public storefront
browsing (shop grid, product detail pages, search) is Module 5 — this
module is purely the admin back office that populates the catalog.

### New shared infrastructure

- **`App\Core\Str::slug()`** — ASCII-transliterating slug generator,
  used by categories, brands, and products alike.
- **`Model::uniqueSlug()`** (added to the base `Model` in
  `app/Core/Model.php`) — appends `-2`, `-3`, ... until a slug is free
  in that model's table. Any future model with a `slug` column gets
  this for free.
- **`App\Services\Upload\ImageUploader`** — validated image upload
  handling: the real MIME type is sniffed server-side via `fileinfo`
  (never trusts the client-supplied type), `getimagesize()` confirms
  the file actually decodes as an image, the stored filename is a
  random hex string with a server-determined extension (never the
  uploaded name — blocks path traversal and extension-spoofing
  attacks), and `move_uploaded_file()` is used, which refuses anything
  that isn't a genuine upload.

### Category tree and cycle prevention

Categories are self-referencing (`parent_id`). `Category::tree()`
returns a flat, depth-annotated list (parents immediately followed by
children) used for indented `<select>` options and list-view
indentation. `Category::selfAndDescendantIds()` computes a category and
everything beneath it, and both `CategoryController::edit()` (to filter
the parent dropdown) and `::update()` (to reject the submission
outright) use it to make it structurally impossible to assign a
category as its own ancestor.

### Products: variants, specifications, images

- **Variants** (`product_attributes` — color/size/etc.) are submitted
  as parallel arrays (`attr_name[]`, `attr_value[]`,
  `attr_price_modifier[]`, `attr_stock[]`, `attr_sku_suffix[]`) from a
  dynamic, JS-driven repeatable row group
  (`public/assets/js/admin-product-form.js`, vanilla ES6, no build
  step). On every save, the full set is deleted and re-inserted
  (`ProductAttribute::deleteForProduct()` then re-create) rather than
  diffed — simpler and correct for the row counts this form deals with.
- **Specifications** are the same repeatable-row pattern
  (`spec_keys[]`/`spec_values[]`), assembled into an associative array
  and stored as JSON in `products.specifications`.
- **Images** support multi-file upload (`images[]`, handled by
  `ImageUploader::storeMany()`), a primary-image toggle, and per-image
  removal (which deletes both the DB row and the file on disk). Both
  image actions re-check that the image actually belongs to the
  product ID in the URL before acting — an IDOR guard against passing
  someone else's image ID.
- **Soft delete**: `products.deleted_at` (from Module 1's schema) is
  used instead of a hard `DELETE`, since `order_items` may reference a
  product later. `Product::findWithRelations()`,
  `paginateWithFilters()`, and `countWithFilters()` all filter
  `deleted_at IS NULL`; a soft-deleted product's edit page 404s and it
  disappears from the list, but the row (and its order history)
  survives.
- **SKU**: auto-generated (`KYM-XXXXXXXX`, checked for uniqueness) if
  left blank on create; editable afterward, with a uniqueness check
  against other products.

### Router change: array unpacking for combined middleware stacks

`routes/admin.php` combines a permission gate with CSRF protection on
every write route, e.g.:

```php
$router->post('/admin/categories', [CategoryController::class, 'store'],
    [...$categoriesPermission, VerifyCsrfMiddleware::class]);
```

where `$categoriesPermission = [[PermissionMiddleware::class,
'categories.manage']]`. This is plain PHP array spreading, not a
router change — Module 3's `[ClassName::class, ...args]` middleware
format composes with ordinary arrays without any further
infrastructure work.

## 2. Folder location / files delivered

```
app/Core/Str.php
app/Core/Model.php                              (updated: uniqueSlug())
app/Services/Upload/ImageUploader.php
app/Models/Category.php
app/Models/Brand.php
app/Models/Product.php
app/Models/ProductImage.php
app/Models/ProductAttribute.php
app/Controllers/Admin/CategoryController.php
app/Controllers/Admin/BrandController.php
app/Controllers/Admin/ProductController.php
app/Views/admin/categories/index.php
app/Views/admin/categories/form.php             (shared create/edit)
app/Views/admin/brands/index.php
app/Views/admin/brands/form.php                 (shared create/edit)
app/Views/admin/products/index.php              (search + category/brand filters + pagination)
app/Views/admin/products/form.php               (shared create/edit)
app/Views/partials/pagination.php
public/assets/js/admin-product-form.js
routes/admin.php                                (updated: catalog routes)
app/Views/admin/layouts/app.php                 (updated: sidebar links)
```

## 3. Routes

All gated by the relevant `*.manage` permission; POST routes also
require CSRF.

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/admin/categories`, `/admin/categories/create`, `/admin/categories/{id}/edit`, `/admin/categories/{id}` (update), `/admin/categories/{id}/delete` | `categories.manage` |
| GET/POST | `/admin/brands`, `/admin/brands/create`, `/admin/brands/{id}/edit`, `/admin/brands/{id}` (update), `/admin/brands/{id}/delete` | `brands.manage` |
| GET/POST | `/admin/products`, `/admin/products/create`, `/admin/products/{id}/edit`, `/admin/products/{id}` (update), `/admin/products/{id}/delete` | `products.manage` |
| POST | `/admin/products/{id}/images/{imageId}/delete`, `/admin/products/{id}/images/{imageId}/primary` | `products.manage` |

## 4. SQL

No schema changes — uses `categories`, `brands`, `products`,
`product_images`, and `product_attributes` exactly as defined in
Module 1's `database/kymera_collection.sql`.

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance, including actual
file uploads decoded with PHP's GD extension (not just linted):

1. Log in as Super Admin (`admin@kymeracollection.com`).
2. **Categories**: create a subcategory under an existing one, confirm
   the slug and indentation. Then try editing that parent category to
   set its own child as its parent → rejected with "A category cannot
   be its own parent or descendant."
3. **Brands**: create one, confirm slug generation.
4. **Products**: create a product with 2 spec rows, 2 color variants,
   and 2 uploaded images (JPG + PNG) via a single multipart POST.
   Confirm in the DB: `products.specifications` is valid JSON,
   `product_attributes` has exactly 2 rows, `product_images` has 2 rows
   with the first marked `is_primary`, and both files exist on disk
   under `public/uploads/products/` with randomized filenames.
5. **Image management**: `POST .../images/{id}/primary` flips which
   image is primary; `POST .../images/{id}/delete` removes both the DB
   row and the file. Confirm a mismatched product ID in the URL
   (`/admin/products/999/images/{realId}/delete`) is a silent no-op —
   the image survives.
6. **Update**: re-submit the product with 3 variant rows instead of 2
   → confirm `product_attributes` ends up with exactly 3 rows for that
   product, not 5 (delete-then-recreate, not append).
7. **Soft delete**: `POST /admin/products/{id}/delete` → the product
   disappears from `/admin/products` and its edit page 404s, but
   `SELECT deleted_at FROM products WHERE id = ...` shows a timestamp,
   not NULL.
8. **RBAC**: log in as Manager (has `products.manage`) → `200` on
   `/admin/products`. Log in as Support (does not) → `403`, and the
   sidebar doesn't render a Products link either.

⚠️ **curl caveat found while testing**: don't pass the target URL to
`curl` twice in one invocation (e.g. once after `-X POST` and again at
the end before a pipe) — curl treats each URL argument as a *separate*
request and will submit the form twice, creating duplicate records.
This is a test-tooling gotcha, not an application behavior; a real
browser only submits once per click. (See the "bugs found" section for
how this was caught and ruled out as an app defect.)

## 6. Bugs found while testing (and fixed before commit)

No new application defects surfaced during this module's testing.

One thing *did* look like a bug during testing and is worth recording:
an early test run created two identical products from what was
intended as a single form submission. Investigation (checking the dev
server's request log) showed **two distinct POST requests actually
arrived**, which turned out to be caused by the test's own `curl`
command specifying the target URL twice — curl fetches every URL
argument it's given, so passing the same URL twice submits the form
twice. Confirmed by re-running the identical form data with a
correctly single-URL command, which created exactly one product. No
code change was needed; this is called out here so the "found a bug,
fixed it" pattern from earlier modules doesn't get assumed here too.

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| "Only JPG, PNG, and WEBP images are allowed" for a file that looks fine | The file's actual sniffed MIME type (via `fileinfo`) doesn't match one of the three allowed types, regardless of its extension | Re-export/convert the image to a real JPG, PNG, or WEBP - renaming a `.gif` to `.jpg` will not pass |
| Product images don't appear after upload | `php-fileinfo` or `php-gd`-equivalent extension not enabled, so `finfo_open()`/`getimagesize()` fail | Confirm `ext-fileinfo` is enabled (required by `composer.json`); on some minimal PHP installs it needs enabling in `php.ini` |
| "This category cannot be deleted while it still has products assigned to it" | `products.category_id` has `ON DELETE RESTRICT` (Module 1 schema) - by design, so historical products don't lose their category silently | Reassign or delete the category's products first, or leave the category in place and mark it inactive instead |
| Uploaded file silently not saved, no error shown | The `<form>` is missing `enctype="multipart/form-data"` | Already set on the product form; if you copy this pattern for a new file-upload form elsewhere, don't forget it |
| Variant/spec rows don't submit correctly | Row inputs edited outside the provided "Add" button (e.g. a hand-built row missing the `name="attr_name[]"` etc. array-bracket naming) | Only add rows via `admin-product-form.js`'s clone-and-clear, which preserves the correct `[]` field names |

## 8. Next module

**Module 5 — Storefront**: home page sections, shop/category browsing,
product detail pages (gallery, variants, specs, reviews), and search —
consuming the catalog data this module now lets admins create. Waiting
for confirmation to proceed.
