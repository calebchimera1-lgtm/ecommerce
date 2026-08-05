# Module 13 — Blog, Testimonials, Static Pages, SEO, Sitemap

## 1. Explanation

Adds a public blog ("The Journal") with moderated comments, admin
management for testimonials (previously demo-only content shown on
the homepage since Module 5), a new Shipping & Returns static page,
SEO meta-field support across products/categories/brands/blog posts,
and a dynamic sitemap.xml + robots.txt. Also fixes a real,
previously-undiscovered timezone bug this module's own testing
surfaced.

### The bug this module found: PHP/MySQL clock mismatch broke every `NOW()` comparison

Testing blog post publishing live - not just checking HTTP status
codes - immediately surfaced something wrong: a post marked
"Published" with a `published_at` timestamp still 404'd on its public
URL. Investigating, `BlogPost::findPublishedBySlug()`'s
`published_at <= NOW()` condition was failing even though the post had
just been published. The root cause: `.env`'s `APP_TIMEZONE=Africa/Nairobi`
(UTC+3) is set via `date_default_timezone_set()` in `App::run()`
(Module 1), so every PHP-computed timestamp written to the database
(`date('Y-m-d H:i:s')`) is in Nairobi time - but MySQL's own `NOW()`
runs in the database server's `SYSTEM` timezone, which in this sandbox
is UTC. A post "published" at Nairobi 10:55 got compared against
MySQL's `NOW()` reporting UTC 07:55 - three hours behind - so the post
looked like it was published in the future and stayed invisible for
three hours.

This isn't a Module-13-only bug. `grep`ping for `NOW()` across
`app/Models/` turned up two more call sites already relying on the
same (broken) assumption: `PasswordReset::valid()`'s `expires_at > NOW()`
(Module 2) and `Coupon`'s `starts_at <= NOW()` / `expires_at >= NOW()`
(Module 6) - meaning password reset links and coupon validity windows
have been silently vulnerable to the same clock-skew class of bug
since those modules shipped, on any deployment where the app server's
configured timezone doesn't match the DB server's OS timezone (a very
plausible real-world setup, not a sandbox quirk).

**Fix**: rather than patch each symptom, `Database::connection()`
(Module 1) now issues `SET time_zone = '<offset>'` on every new PDO
connection, computing the offset from `config('app.timezone')` the
same way `App::run()` already does - so MySQL's session clock and
PHP's clock always agree, for every query, everywhere, not just the
ones this module added. Verified directly: before the fix, a PHP
script bootstrapped the same way the app is (`date_default_timezone_set()`
then querying `SELECT NOW()`) showed a 3-hour gap between PHP's `date()`
and MySQL's `NOW()`; after the fix, they're identical. Re-tested the
original failing scenario (unpublish, republish a post) end-to-end
after the fix and confirmed the post went live immediately, and
confirmed via a fresh PHP-vs-MySQL clock comparison that the same fix
protects `PasswordReset` and `Coupon`'s existing `NOW()` comparisons
too, without touching either of those files.

### Blog content: escaped, not raw HTML - a deliberate consistency call

The post body textarea renders with `e($post['content'])` plus
`white-space:pre-line`, not raw unescaped HTML. A blog naturally wants
some formatting, and staff-authored content (gated by `blog.manage`,
audit-logged) is arguably a different trust level than customer input -
many real CMSs do render author content unescaped on exactly that
reasoning. But this codebase already made the opposite call for
`products.description` (Module 4's product page uses `e($product['description'])`
with the same `pre-line` treatment) - and introducing the *only*
unescaped-HTML output point in the customer-facing site, without a
rich-text editor to justify needing real HTML, would be an
inconsistent, avoidable stored-XSS surface (a compromised or careless
staff account could inject a script that runs in every visitor's
browser). Escaped-with-pre-line was chosen to match the existing
precedent rather than open a new trust boundary this module doesn't
actually need. Customer-authored content (blog comments, reviews) was
always escaped and remains so - that distinction is unchanged.

### SEO fields: wiring up columns that existed but were unused

`products`, `categories`, and `brands` have had `meta_title`/
`meta_description` columns since Module 1, but no admin form ever
exposed them and no customer-facing controller ever read them - Module 5's
`ProductController::show()` always derived the meta description from
`short_description`/`description` instead. This module adds the form
fields (admin) and wires the customer-facing `<title>`/`<meta description>`
tags to prefer the explicit SEO field when an admin has set one,
falling back to the existing derived behavior otherwise - verified
live by setting a custom meta title/description on both a product and
a category and confirming the exact custom text appeared in the
rendered `<title>` and `<meta name="description">` tags, not the
defaults.

### Sitemap and robots.txt are generated, not static files

`GET /sitemap.xml` builds its URL list from live data each request
(active categories, active brands, active products, published blog
posts, plus the static pages) rather than being a stale, manually
maintained file - verified it reflects a freshly created product and
blog post without any deploy step. `GET /robots.txt` is also served
dynamically (not a `public/robots.txt` static file) specifically so
its `Sitemap:` directive can embed the deployment's real `APP_URL`
rather than a value baked in for one specific environment.

### Testimonials: reusing `blog.manage`, not a new permission

Unlike Module 12's `expenses.manage` (a genuinely different trust
level from viewing reports), testimonial management is public-facing
marketing copy - the same trust level as blog content. Rather than add
a fourth "just curate some public copy" permission, `Admin\TestimonialController`
reuses the existing `blog.manage` permission. Documented directly in
the controller's docblock so the reasoning isn't just "here because it
was here" for the next person reading it.

### Stale copy fixed in passing

Module 5's FAQ page had two answers written as placeholders *before*
checkout (Module 7) and order tracking (Module 8) existed ("once the
shipping module launches," "once order tracking launches"). Since this
module's whole purpose is customer-facing content accuracy, these were
updated to describe what actually exists today - real shipping options
and a real, working My Orders tracking page - rather than leaving
forward-looking placeholder language live on a feature that shipped
five modules ago.

## 2. Folder location / files delivered

```
app/Models/BlogCategory.php
app/Models/BlogPost.php
app/Models/BlogComment.php
app/Controllers/Customer/BlogController.php
app/Controllers/Customer/SitemapController.php
app/Controllers/Admin/BlogCategoryController.php
app/Controllers/Admin/BlogPostController.php
app/Controllers/Admin/TestimonialController.php
app/Views/customer/blog/{index,show}.php
app/Views/customer/static/shipping-returns.php
app/Views/admin/blog-categories/{index,form}.php
app/Views/admin/blog-posts/{index,form}.php
app/Views/admin/testimonials/{index,form}.php
```

Updated: `app/Core/Database.php` (session timezone alignment fix -
see above), `config/database.php` (added `timezone` key),
`app/Models/Product.php` (`allActiveForSitemap()`),
`app/Controllers/Admin/{Category,Brand,Product}Controller.php` (SEO
field handling), `app/Views/admin/{categories,brands,products}/form.php`
(SEO fields), `app/Controllers/Customer/ProductController.php` and
`ShopController.php` (read SEO fields for `<title>`/meta description),
`app/Controllers/Customer/StaticController.php`
(`shippingReturns()`), `app/Views/customer/static/faqs.php` (stale
copy fix), `app/Views/customer/layouts/site.php` (Journal nav/footer
links, Shipping & Returns footer link), `app/Views/admin/layouts/app.php`
(sidebar entries), `routes/admin.php`, `routes/web.php`.

## 3. Routes

```
GET  /blog                               public
GET  /blog/category/{slug}               public
GET  /blog/{slug}                        public
POST /blog/{slug}/comments               requires login
GET  /shipping-returns                   public
GET  /sitemap.xml                        public
GET  /robots.txt                         public

GET  /admin/blog-categories[...]         blog.manage
GET  /admin/blog-posts[...]              blog.manage
POST /admin/blog-posts/{id}/comments/{commentId}/approve   blog.manage
POST /admin/blog-posts/{id}/comments/{commentId}/delete    blog.manage
GET  /admin/testimonials[...]            blog.manage
```

## 4. SQL

No schema changes - `blog_categories`, `blog_posts`, `blog_comments`,
and `testimonials` have all existed since Module 1 with the exact
columns this module needed (including `meta_title`/`meta_description`
on `blog_posts`, and the same on `products`/`categories`/`brands`).

## 5. Testing instructions

Verified end-to-end against a real MariaDB instance through the actual
admin UI and public pages:

1. Created a real blog category and a draft post through the admin
   forms - confirmed the draft correctly 404'd on its public URL while
   still showing in the admin list.
2. Published it - hit the timezone bug described above; diagnosed,
   fixed `Database::connection()`, restarted, and confirmed via a
   direct PHP-vs-MySQL clock comparison that the fix closed the gap.
   Un-published and re-published the same post through the real admin
   UI afterward and confirmed it went live immediately this time.
3. Confirmed the published post's content, category name, and a
   "More Articles" related-post rail all render correctly on its
   public page.
4. Logged in as a real customer, submitted a real blog comment -
   confirmed it was invisible on the public page and showed "Pending"
   in the admin post editor's moderation section; approved it there
   and confirmed it then appeared publicly, with an audit log entry
   recording the approval.
5. Created a real testimonial through the admin form and confirmed it
   appeared immediately on the live homepage testimonials section
   (reusing Module 5's existing `Testimonial::activeOrdered()` query -
   no changes needed there).
6. Set custom SEO meta title/description on a real product and a real
   category through their admin edit forms, then confirmed the exact
   custom text appeared in the rendered `<title>` and
   `<meta name="description">` tags on their public pages (not the
   previous derived-from-description fallback).
7. Confirmed `/sitemap.xml` includes the real test product and
   published blog post with correct `<lastmod>` dates, and that
   `/robots.txt` correctly disallows `/admin`, `/account`, `/cart`,
   `/checkout` and points its `Sitemap:` line at the real configured
   `APP_URL`.
8. **RBAC**: confirmed Support (no `blog.manage`) gets 403 on all three
   new admin sections; confirmed Manager (which has `blog.manage` per
   Module 1's seed) gets full access with all three sidebar links
   visible.

## 6. Bugs found while testing (and fixed before commit)

- **PHP/MySQL timezone mismatch breaking every `NOW()` comparison**
  (detailed above): fixed at the root in `Database::connection()`
  rather than patched per-symptom, since the same bug class already
  existed in two earlier modules' password reset and coupon logic.

No other bugs found. The MariaDB host-resolution and SMTP-timeout
environment quirks from Modules 10-11 didn't recur (the `.env` fixes
from those modules persisted).

## 7. Common errors and fixes

| Error | Cause | Fix |
|---|---|---|
| A post marked "Published" still 404s on its public URL | `Database::connection()`'s timezone fix isn't applied (old code, or `config/database.php` missing the `timezone` key) | Pull the fixed `Database.php`/`config/database.php`; as a sanity check, compare `date('Y-m-d H:i:s')` in PHP against `SELECT NOW()` in the same request - they must match exactly |
| A comment never appears even after approving it | The comment belongs to a different post than the one being viewed (a stale page load before approval) | Reload the public post page after approving in the admin editor |
| SEO meta title/description doesn't show the custom text | The field was left blank - blank explicitly falls back to the name/description-derived default by design, not a bug | Fill in the Meta title/Meta description fields on the product/category/brand edit form |
| `/sitemap.xml` is missing a product or post that should be there | The product isn't `is_active = 1` / not-deleted, or the post isn't `is_published = 1` with a past `published_at` | Only active products and published (non-draft, non-future-dated) posts are included by design |

## 8. Next module

**Module 14 — Security hardening pass & final QA**: a full pass across
every module built so far - re-verifying CSRF/IDOR/RBAC coverage,
checking for any remaining unescaped output, reviewing session/cookie
configuration for production readiness, and a final end-to-end
regression pass of the complete storefront + admin panel. Waiting for
confirmation to proceed.
