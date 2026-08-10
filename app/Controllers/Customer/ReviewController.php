<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ProductReview;
use App\Models\VendorReview;

final class ReviewController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/account/reviews/index', [
            'pageTitle' => 'My Reviews | Kymera Collection',
            'reviews' => ProductReview::forUser((int) Auth::id()),
            'vendorReviews' => VendorReview::forUser((int) Auth::id()),
        ], 'customer/layouts/site');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->route('id');
        $review = $this->ownedReview($id);

        $this->view('customer/account/reviews/edit', [
            'pageTitle' => 'Edit Review | Kymera Collection',
            'review' => $review,
        ], 'customer/layouts/site');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $this->ownedReview($id);

        $data = $this->validate($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'max:2000',
        ]);

        ProductReview::update($id, [
            'rating' => (int) $data['rating'],
            'title' => self::nullable($request->input('title')),
            'comment' => self::nullable($request->input('comment')),
            // Editing sends it back to moderation, so a customer can't
            // slip different content past an already-approved review.
            'is_approved' => 0,
        ]);

        Session::flash('success', 'Review updated and resubmitted for approval.');
        $this->redirect('/account/reviews');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');
        $this->ownedReview($id);

        ProductReview::delete($id);

        Session::flash('success', 'Review deleted.');
        $this->redirect('/account/reviews');
    }

    public function editVendor(Request $request): void
    {
        $id = (int) $request->route('id');
        $review = $this->ownedVendorReview($id);

        $this->view('customer/account/reviews/edit-vendor', [
            'pageTitle' => 'Edit Store Review | Kymera Collection',
            'review' => $review,
        ], 'customer/layouts/site');
    }

    public function updateVendor(Request $request): void
    {
        $id = (int) $request->route('id');
        $this->ownedVendorReview($id);

        $data = $this->validate($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'max:2000',
        ]);

        VendorReview::update($id, [
            'rating' => (int) $data['rating'],
            'title' => self::nullable($request->input('title')),
            'comment' => self::nullable($request->input('comment')),
            'is_approved' => 0,
        ]);

        Session::flash('success', 'Review updated and resubmitted for approval.');
        $this->redirect('/account/reviews');
    }

    public function destroyVendor(Request $request): void
    {
        $id = (int) $request->route('id');
        $this->ownedVendorReview($id);

        VendorReview::delete($id);

        Session::flash('success', 'Review deleted.');
        $this->redirect('/account/reviews');
    }

    private function ownedReview(int $id): array
    {
        $review = ProductReview::find($id);
        $userId = (int) Auth::id();

        if ($review === null || (int) $review['user_id'] !== $userId) {
            Response::abort(404, 'Review not found.');
        }

        return $review;
    }

    private function ownedVendorReview(int $id): array
    {
        $review = VendorReview::find($id);
        $userId = (int) Auth::id();

        if ($review === null || (int) $review['user_id'] !== $userId) {
            Response::abort(404, 'Review not found.');
        }

        return $review;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
