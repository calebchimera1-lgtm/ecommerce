<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

final class ShopController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): void
    {
        $this->renderListing($request, [], 'Shop All');
    }

    public function category(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $category = Category::findActiveBySlug($slug);

        if ($category === null) {
            Response::abort(404, 'Category not found.');
        }

        $this->renderListing($request, ['category_id' => (string) $category['id']], $category['name'], $category);
    }

    public function brand(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $brand = Brand::findActiveBySlug($slug);

        if ($brand === null) {
            Response::abort(404, 'Brand not found.');
        }

        $this->renderListing($request, ['brand_id' => (string) $brand['id']], $brand['name'], null, $brand);
    }

    public function search(Request $request): void
    {
        $q = trim((string) $request->query('q', ''));
        $heading = $q !== '' ? 'Search results for "' . $q . '"' : 'Search';

        $this->renderListing($request, ['search' => $q], $heading);
    }

    private function renderListing(
        Request $request,
        array $baseFilters,
        string $heading,
        ?array $activeCategory = null,
        ?array $activeBrand = null
    ): void {
        $page = max(1, (int) $request->query('page', 1));
        $sort = (string) $request->query('sort', 'newest');

        $filters = [
            'search' => $baseFilters['search'] ?? trim((string) $request->query('q', '')),
            'category_id' => $baseFilters['category_id'] ?? (string) $request->query('category_id', ''),
            'brand_id' => $baseFilters['brand_id'] ?? (string) $request->query('brand_id', ''),
            'min_price' => (string) $request->query('min_price', ''),
            'max_price' => (string) $request->query('max_price', ''),
        ];

        $metaSource = $activeCategory ?? $activeBrand;

        $this->view('customer/shop/index', [
            'pageTitle' => (($metaSource['meta_title'] ?? null) ?: $heading) . ' | Kymera Collection',
            'metaDescription' => $metaSource['meta_description'] ?? $metaSource['description'] ?? null,
            'heading' => $heading,
            'products' => Product::publicPaginate($page, self::PER_PAGE, $filters, $sort),
            'categories' => Category::activeOrdered(),
            'brands' => Brand::activeOrdered(),
            'filters' => $filters,
            'sort' => $sort,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Product::publicCount($filters),
            'activeCategory' => $activeCategory,
            'activeBrand' => $activeBrand,
        ], 'customer/layouts/site');
    }
}
