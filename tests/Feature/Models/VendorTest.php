<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Vendor;
use Tests\Support\Factory;
use Tests\TestCase;

final class VendorTest extends TestCase
{
    public function test_generate_slug_appends_a_numeric_suffix_on_collision(): void
    {
        Factory::vendor('approved', ['store_name' => 'Milano Leather', 'slug' => 'milano-leather']);

        $slug = Vendor::generateSlug('Milano Leather');

        $this->assertSame('milano-leather-2', $slug);
    }

    public function test_find_active_by_slug_returns_only_approved_vendors(): void
    {
        ['vendor' => $approved] = Factory::vendor('approved', ['store_name' => 'Approved Store', 'slug' => 'approved-store']);
        ['vendor' => $pending] = Factory::vendor('pending', ['store_name' => 'Pending Store', 'slug' => 'pending-store']);
        ['vendor' => $rejected] = Factory::vendor('rejected', ['store_name' => 'Rejected Store', 'slug' => 'rejected-store']);
        ['vendor' => $suspended] = Factory::vendor('suspended', ['store_name' => 'Suspended Store', 'slug' => 'suspended-store']);

        $this->assertNotNull(Vendor::findActiveBySlug('approved-store'));
        $this->assertNull(Vendor::findActiveBySlug('pending-store'), 'A pending vendor should not have a public storefront.');
        $this->assertNull(Vendor::findActiveBySlug('rejected-store'), 'A rejected vendor should not have a public storefront.');
        $this->assertNull(Vendor::findActiveBySlug('suspended-store'), 'A suspended vendor should not have a public storefront.');
    }

    public function test_find_active_by_slug_returns_null_for_unknown_slug(): void
    {
        $this->assertNull(Vendor::findActiveBySlug('no-such-store'));
    }

    public function test_all_approved_for_sitemap_excludes_non_approved_vendors(): void
    {
        Factory::vendor('approved', ['store_name' => 'Visible Store', 'slug' => 'visible-store']);
        Factory::vendor('suspended', ['store_name' => 'Hidden Store', 'slug' => 'hidden-store']);

        $slugs = array_column(Vendor::allApprovedForSitemap(), 'slug');

        $this->assertContains('visible-store', $slugs);
        $this->assertNotContains('hidden-store', $slugs);
    }

    public function test_approve_sets_approved_by_and_approved_at_and_clears_rejection_reason(): void
    {
        ['vendor' => $vendor] = Factory::vendor('rejected');
        Vendor::update((int) $vendor['id'], ['rejection_reason' => 'Incomplete application']);
        // Just needs a valid users.id for the approved_by FK - the
        // approving user's own role isn't what this test is about.
        $approver = Factory::customer();

        Vendor::approve((int) $vendor['id'], (int) $approver['id']);

        $fresh = Vendor::find((int) $vendor['id']);
        $this->assertSame('approved', $fresh['status']);
        $this->assertSame((int) $approver['id'], (int) $fresh['approved_by']);
        $this->assertNotNull($fresh['approved_at']);
        $this->assertNull($fresh['rejection_reason']);
    }

    public function test_reject_stores_the_reason_and_leaves_approved_by_untouched(): void
    {
        ['vendor' => $vendor] = Factory::vendor('pending');

        Vendor::reject((int) $vendor['id'], 'Missing business registration.');

        $fresh = Vendor::find((int) $vendor['id']);
        $this->assertSame('rejected', $fresh['status']);
        $this->assertSame('Missing business registration.', $fresh['rejection_reason']);
        $this->assertNull($fresh['approved_by']);
    }

    public function test_suspend_and_reactivate_round_trip(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');

        Vendor::suspend((int) $vendor['id']);
        $this->assertSame('suspended', Vendor::find((int) $vendor['id'])['status']);

        Vendor::reactivate((int) $vendor['id']);
        $this->assertSame('approved', Vendor::find((int) $vendor['id'])['status']);
    }
}
