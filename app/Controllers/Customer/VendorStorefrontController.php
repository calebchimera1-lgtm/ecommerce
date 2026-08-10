<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Models\Vendor;

/**
 * A vendor's public storefront - the customer-facing counterpart to
 * everything a vendor has built since Module 15 (store profile,
 * approved listings). Reuses the same Product::publicPaginate()/
 * publicCount() the main shop grid uses (Customer\ShopController),
 * just with an added vendor_id filter, so this page behaves exactly
 * like "the shop, scoped to one seller" rather than a parallel
 * implementation with its own rules.
 */
final class VendorStorefrontController extends Controller
{
    private const PER_PAGE = 12;

    public function show(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $vendor = Vendor::findActiveBySlug($slug);

        if ($vendor === null) {
            Response::abort(404, 'Store not found.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $sort = (string) $request->query('sort', 'newest');
        $filters = ['vendor_id' => (string) $vendor['id']];

        $this->view('customer/vendor/show', [
            'pageTitle' => $vendor['store_name'] . ' | Kymera Collection',
            'metaDescription' => $vendor['description'],
            'vendor' => $vendor,
            'products' => Product::publicPaginate($page, self::PER_PAGE, $filters, $sort),
            'total' => Product::publicCount($filters),
            'sort' => $sort,
            'page' => $page,
            'perPage' => self::PER_PAGE,
        ], 'customer/layouts/site');
    }
}
