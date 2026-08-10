<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\VendorReview;

/**
 * Moderation queue for vendor ratings (Module 21) - the vendor-review
 * counterpart to Admin\ReviewController, which handles product
 * reviews. Kept as a separate controller/page rather than folded into
 * the existing one: a vendor review and a product review are
 * different entities under moderation (one is about a seller, the
 * other about an item), the same reasoning that already keeps
 * Admin\PayoutController separate from Admin\VendorController despite
 * both being "about a vendor".
 */
final class VendorReviewController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['status' => (string) $request->query('status', '')];

        $this->view('admin/vendor-reviews/index', [
            'pageTitle' => 'Vendor Reviews | Kymera Collection Admin',
            'reviews' => VendorReview::paginateAdmin($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => VendorReview::countAdmin($filters),
        ], 'admin/layouts/app');
    }

    public function approve(Request $request): void
    {
        $review = $this->loadReview($request);
        VendorReview::approve((int) $review['id']);

        AuditLog::record(Auth::id(), 'vendor_review.approved', 'vendor_review', (int) $review['id'], [
            'is_approved' => (int) $review['is_approved'],
        ], ['is_approved' => 1]);

        Session::flash('success', 'Review approved.');
        $this->back();
    }

    public function destroy(Request $request): void
    {
        $review = $this->loadReview($request);
        VendorReview::delete((int) $review['id']);

        AuditLog::record(Auth::id(), 'vendor_review.deleted', 'vendor_review', (int) $review['id'], [
            'is_approved' => (int) $review['is_approved'],
            'rating' => (int) $review['rating'],
        ], null);

        Session::flash('success', 'Review removed.');
        $this->back();
    }

    private function loadReview(Request $request): array
    {
        $id = (int) $request->route('id');
        $review = VendorReview::find($id);

        if ($review === null) {
            Response::abort(404, 'Review not found.');
        }

        return $review;
    }
}
