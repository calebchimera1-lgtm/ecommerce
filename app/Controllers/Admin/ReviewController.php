<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\ProductReview;

final class ReviewController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['status' => (string) $request->query('status', '')];

        $this->view('admin/reviews/index', [
            'pageTitle' => 'Reviews | Kymera Collection Admin',
            'reviews' => ProductReview::paginateAdmin($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => ProductReview::countAdmin($filters),
        ], 'admin/layouts/app');
    }

    public function approve(Request $request): void
    {
        $review = $this->loadReview($request);
        ProductReview::approve((int) $review['id']);

        AuditLog::record(Auth::id(), 'review.approved', 'product_review', (int) $review['id'], [
            'is_approved' => (int) $review['is_approved'],
        ], ['is_approved' => 1]);

        Session::flash('success', 'Review approved.');
        $this->back();
    }

    /**
     * There is no distinct "rejected" state in the schema (Module 1's
     * `product_reviews.is_approved` is a plain boolean) - rejecting a
     * pending review, or removing an approved one that turns out to be
     * abusive/spam, both mean the same thing here: the review is gone.
     */
    public function destroy(Request $request): void
    {
        $review = $this->loadReview($request);
        ProductReview::delete((int) $review['id']);

        AuditLog::record(Auth::id(), 'review.deleted', 'product_review', (int) $review['id'], [
            'is_approved' => (int) $review['is_approved'],
            'rating' => (int) $review['rating'],
        ], null);

        Session::flash('success', 'Review removed.');
        $this->back();
    }

    private function loadReview(Request $request): array
    {
        $id = (int) $request->route('id');
        $review = ProductReview::find($id);

        if ($review === null) {
            Response::abort(404, 'Review not found.');
        }

        return $review;
    }
}
