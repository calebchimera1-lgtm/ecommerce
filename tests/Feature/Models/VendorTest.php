<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Vendor;
use App\Models\VendorReview;
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

    public function test_tier_label_requires_both_a_minimum_review_count_and_average(): void
    {
        $this->assertNull(Vendor::tierLabel(0, 0.0), 'No reviews at all should carry no badge.');
        $this->assertSame('Rising Seller', Vendor::tierLabel(1, 5.0), 'One glowing review is Rising, not Top Rated - a single review is not enough sample size.');
        $this->assertSame('Top Rated Seller', Vendor::tierLabel(5, 4.5));
        $this->assertNull(Vendor::tierLabel(10, 3.0), 'A high review count with a mediocre average earns no badge.');
        $this->assertNull(Vendor::tierLabel(0, 5.0), 'A perfect average with zero reviews earns no badge - there is nothing to average.');
    }

    public function test_tier_label_boundary_values(): void
    {
        $this->assertSame('Top Rated Seller', Vendor::tierLabel(5, 4.5), 'Exactly at the Top Rated threshold should qualify.');
        $this->assertSame('Rising Seller', Vendor::tierLabel(4, 4.5), 'One review short of Top Rated should fall back to Rising.');
        $this->assertSame('Rising Seller', Vendor::tierLabel(1, 4.0), 'Exactly at the Rising threshold should qualify.');
        $this->assertNull(Vendor::tierLabel(1, 3.9), 'Just under the Rising threshold should earn no badge.');
    }

    public function test_find_with_user_reports_the_real_approved_review_count_and_average(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $reviewerA = Factory::customer();
        $reviewerB = Factory::customer();
        $reviewerC = Factory::customer();

        VendorReview::create([
            'vendor_id' => $vendor['id'], 'user_id' => $reviewerA['id'], 'vendor_order_id' => null,
            'rating' => 5, 'title' => null, 'comment' => null, 'is_approved' => 1,
        ]);
        VendorReview::create([
            'vendor_id' => $vendor['id'], 'user_id' => $reviewerB['id'], 'vendor_order_id' => null,
            'rating' => 3, 'title' => null, 'comment' => null, 'is_approved' => 1,
        ]);
        // Pending (not yet approved) - must not count toward the average.
        VendorReview::create([
            'vendor_id' => $vendor['id'], 'user_id' => $reviewerC['id'], 'vendor_order_id' => null,
            'rating' => 1, 'title' => null, 'comment' => null, 'is_approved' => 0,
        ]);

        $fresh = Vendor::findWithUser((int) $vendor['id']);

        $this->assertSame(2, (int) $fresh['review_count']);
        $this->assertSame(4.0, (float) $fresh['average_rating']);
    }
}
