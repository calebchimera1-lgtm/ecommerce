<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\Vendor;
use App\Models\VendorReview;
use App\Models\Wishlist;

final class ProductController extends Controller
{
    public function show(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $product = Product::findActiveBySlug($slug);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        Product::incrementViewCount((int) $product['id']);

        $attributes = ProductAttribute::forProduct((int) $product['id']);
        $groupedAttributes = [];

        foreach ($attributes as $attribute) {
            $groupedAttributes[$attribute['attribute_name']][] = $attribute;
        }

        $currentUser = Auth::user();
        $canReview = $currentUser !== null
            && !ProductReview::userHasReviewed((int) $product['id'], (int) $currentUser['id']);
        $isWishlisted = $currentUser !== null
            && Wishlist::exists((int) $currentUser['id'], (int) $product['id']);

        $related = Product::publicPaginate(1, 5, ['category_id' => (string) $product['category_id']], 'newest');
        $relatedProducts = array_slice(
            array_values(array_filter(
                $related,
                static fn (array $p): bool => (int) $p['id'] !== (int) $product['id']
            )),
            0,
            4
        );

        $vendorTier = null;

        if ($product['vendor_id'] !== null) {
            $vendorRatingSummary = VendorReview::ratingSummary((int) $product['vendor_id']);
            $vendorTier = Vendor::tierLabel($vendorRatingSummary['count'], $vendorRatingSummary['average']);
        }

        $this->view('customer/product/show', [
            'pageTitle' => ($product['meta_title'] ?? $product['name']) . ' | Kymera Collection',
            'metaDescription' => $product['meta_description'] ?? $product['short_description'] ?? mb_substr(strip_tags((string) $product['description']), 0, 160),
            'product' => $product,
            'images' => ProductImage::forProduct((int) $product['id']),
            'groupedAttributes' => $groupedAttributes,
            'specifications' => $product['specifications'] !== null ? (json_decode($product['specifications'], true) ?? []) : [],
            'reviews' => ProductReview::approvedForProduct((int) $product['id']),
            'ratingSummary' => ProductReview::ratingSummary((int) $product['id']),
            'vendorTier' => $vendorTier,
            'canReview' => $canReview,
            'isLoggedIn' => $currentUser !== null,
            'isWishlisted' => $isWishlisted,
            'relatedProducts' => $relatedProducts,
        ], 'customer/layouts/site');
    }

    public function storeReview(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $product = Product::findActiveBySlug($slug);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        $user = Auth::user();

        if ($user === null) {
            Response::redirect('/login');
        }

        if (ProductReview::userHasReviewed((int) $product['id'], (int) $user['id'])) {
            Session::flash('errors', ['review' => ['You have already reviewed this product.']]);
            $this->redirect('/product/' . $slug);
        }

        $data = $this->validate($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'max:2000',
        ]);

        ProductReview::create([
            'product_id' => $product['id'],
            'user_id' => $user['id'],
            'order_item_id' => null,
            'rating' => (int) $data['rating'],
            'title' => self::nullable($request->input('title')),
            'comment' => self::nullable($request->input('comment')),
            'is_approved' => 0,
        ]);

        Session::flash('success', 'Thank you! Your review has been submitted and will appear once approved.');
        $this->redirect('/product/' . $slug);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
