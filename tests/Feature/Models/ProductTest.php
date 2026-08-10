<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Product;
use Tests\Support\Factory;
use Tests\TestCase;

final class ProductTest extends TestCase
{
    public function test_generate_slug_appends_a_numeric_suffix_on_collision(): void
    {
        $category = Factory::category();
        Factory::product($category['id'], null, ['slug' => 'italian-tote']);

        $slug = Product::generateSlug('Italian Tote');

        $this->assertSame('italian-tote-2', $slug);
    }

    public function test_generate_slug_ignores_the_given_id(): void
    {
        $category = Factory::category();
        $product = Factory::product($category['id'], null, ['slug' => 'italian-tote']);

        // Simulates re-saving a product's edit form without renaming it
        // - it shouldn't collide with its own existing slug.
        $slug = Product::generateSlug('Italian Tote', (int) $product['id']);

        $this->assertSame('italian-tote', $slug);
    }

    public function test_generate_sku_produces_a_unique_value(): void
    {
        $sku1 = Product::generateSku();
        $sku2 = Product::generateSku();

        $this->assertNotSame($sku1, $sku2);
        $this->assertStringStartsWith('KYM-', $sku1);
    }

    public function test_public_queries_exclude_inactive_products(): void
    {
        $category = Factory::category();
        $product = Factory::product($category['id'], null, ['is_active' => 0]);

        $found = Product::findActiveBySlug($product['slug']);

        $this->assertNull($found);
    }

    public function test_public_queries_exclude_pending_vendor_listings(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $category = Factory::category();
        $product = Factory::product($category['id'], (int) $vendor['id'], [
            'is_active' => 1,
            'approval_status' => 'pending',
        ]);

        $this->assertNull(Product::findActiveBySlug($product['slug']));

        $results = Product::publicPaginate(1, 20, ['vendor_id' => (string) $vendor['id']], 'newest');
        $this->assertCount(0, $results);
    }

    public function test_public_queries_include_approved_vendor_listings(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $category = Factory::category();
        $product = Factory::product($category['id'], (int) $vendor['id'], [
            'is_active' => 1,
            'approval_status' => 'approved',
        ]);

        $this->assertNotNull(Product::findActiveBySlug($product['slug']));
    }

    /**
     * Regression test for the gap found and fixed in Module 19: a
     * suspended vendor's already-approved products must disappear
     * from every public query the moment their vendor status changes,
     * without touching the product row itself.
     */
    public function test_public_queries_exclude_products_from_a_suspended_vendor(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $category = Factory::category();
        $product = Factory::product($category['id'], (int) $vendor['id'], [
            'is_active' => 1,
            'approval_status' => 'approved',
        ]);

        $this->assertNotNull(Product::findActiveBySlug($product['slug']), 'Sanity check: visible while the vendor is approved.');

        \App\Models\Vendor::update((int) $vendor['id'], ['status' => 'suspended']);

        $this->assertNull(Product::findActiveBySlug($product['slug']), 'Must disappear the moment the vendor is suspended.');

        $results = Product::publicPaginate(1, 20, ['vendor_id' => (string) $vendor['id']], 'newest');
        $this->assertCount(0, $results, 'publicPaginate() must also honor the vendor status gate.');

        $sitemapRows = array_filter(
            Product::allActiveForSitemap(),
            static fn (array $row): bool => $row['slug'] === $product['slug']
        );
        $this->assertCount(0, $sitemapRows, 'A suspended vendor\'s product must not appear in the sitemap.');

        \App\Models\Vendor::update((int) $vendor['id'], ['status' => 'approved']);

        $this->assertNotNull(Product::findActiveBySlug($product['slug']), 'Must reappear immediately once the vendor is reactivated.');
    }

    public function test_public_queries_include_platform_owned_products_regardless_of_any_vendor(): void
    {
        $category = Factory::category();
        $product = Factory::product($category['id'], null, ['is_active' => 1, 'approval_status' => 'approved']);

        $this->assertNotNull(Product::findActiveBySlug($product['slug']));
    }

    public function test_status_counts_for_vendor_groups_by_approval_status(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $category = Factory::category();

        Factory::product($category['id'], (int) $vendor['id'], ['approval_status' => 'approved']);
        Factory::product($category['id'], (int) $vendor['id'], ['approval_status' => 'approved']);
        Factory::product($category['id'], (int) $vendor['id'], ['approval_status' => 'pending']);
        Factory::product($category['id'], (int) $vendor['id'], ['approval_status' => 'rejected']);

        $counts = Product::statusCountsForVendor((int) $vendor['id']);

        $this->assertSame(2, $counts['approved']);
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['rejected']);
    }

    public function test_find_for_vendor_returns_null_for_another_vendors_product(): void
    {
        ['vendor' => $vendorA] = Factory::vendor('approved');
        ['vendor' => $vendorB] = Factory::vendor('approved');
        $category = Factory::category();
        $product = Factory::product($category['id'], (int) $vendorA['id']);

        $this->assertNotNull(Product::findForVendor((int) $product['id'], (int) $vendorA['id']));
        $this->assertNull(
            Product::findForVendor((int) $product['id'], (int) $vendorB['id']),
            'A vendor must never be able to load another vendor\'s product by id.'
        );
    }
}
