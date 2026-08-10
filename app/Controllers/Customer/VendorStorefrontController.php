<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorReview;

/**
 * A vendor's public storefront - the customer-facing counterpart to
 * everything a vendor has built since Module 15 (store profile,
 * approved listings). Reuses the same Product::publicPaginate()/
 * publicCount() the main shop grid uses (Customer\ShopController),
 * just with an added vendor_id filter, so this page behaves exactly
 * like "the shop, scoped to one seller" rather than a parallel
 * implementation with its own rules.
 *
 * Also carries vendor ratings (Module 21) - shown on the same page a
 * product's reviews live on its own detail page, and submitted the
 * same way (Customer\ProductController::storeReview() is this
 * method's direct model).
 */
final class VendorStorefrontController extends Controller
{
    private const PER_PAGE = 12;

    public function show(Request $request): void
    {
        $vendor = self::loadVendor($request);
        $page = max(1, (int) $request->query('page', 1));
        $sort = (string) $request->query('sort', 'newest');
        $filters = ['vendor_id' => (string) $vendor['id']];

        $currentUser = Auth::user();
        $canReview = $currentUser !== null
            && !VendorReview::userHasReviewed((int) $vendor['id'], (int) $currentUser['id'])
            && VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $currentUser['id']);

        $this->view('customer/vendor/show', [
            'pageTitle' => $vendor['store_name'] . ' | Kymera Collection',
            'metaDescription' => $vendor['description'],
            'vendor' => $vendor,
            'products' => Product::publicPaginate($page, self::PER_PAGE, $filters, $sort),
            'total' => Product::publicCount($filters),
            'sort' => $sort,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'reviews' => VendorReview::approvedForVendor((int) $vendor['id']),
            'ratingSummary' => VendorReview::ratingSummary((int) $vendor['id']),
            'canReview' => $canReview,
            'isLoggedIn' => $currentUser !== null,
        ], 'customer/layouts/site');
    }

    public function storeReview(Request $request): void
    {
        $vendor = self::loadVendor($request);
        $user = Auth::user();

        if ($user === null) {
            Response::redirect('/login');
        }

        if (VendorReview::userHasReviewed((int) $vendor['id'], (int) $user['id'])) {
            Session::flash('errors', ['review' => ['You have already reviewed this store.']]);
            $this->redirect('/store/' . $vendor['slug']);
        }

        if (!VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $user['id'])) {
            Session::flash('errors', ['review' => ['You can review a store once an order from them has been delivered to you.']]);
            $this->redirect('/store/' . $vendor['slug']);
        }

        $data = $this->validate($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'max:2000',
        ]);

        VendorReview::create([
            'vendor_id' => $vendor['id'],
            'user_id' => $user['id'],
            'vendor_order_id' => null,
            'rating' => (int) $data['rating'],
            'title' => self::nullable($request->input('title')),
            'comment' => self::nullable($request->input('comment')),
            'is_approved' => 0,
        ]);

        Session::flash('success', 'Thank you - your review has been submitted for approval.');
        $this->redirect('/store/' . $vendor['slug']);
    }

    private static function loadVendor(Request $request): array
    {
        $slug = (string) $request->route('slug');
        $vendor = Vendor::findActiveBySlug($slug);

        if ($vendor === null) {
            Response::abort(404, 'Store not found.');
        }

        return $vendor;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
