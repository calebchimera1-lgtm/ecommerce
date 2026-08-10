<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Cache;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Testimonial;

final class HomeController extends Controller
{
    /**
     * Homepage product rails, 5 minutes - short enough that a fresh
     * arrival or a newly-marked sale shows up quickly without every
     * homepage visit re-running four full product queries (each with
     * the same join/subquery cost as the main shop grid). No explicit
     * invalidation hook, unlike Category::activeOrdered(): a stock
     * change, a new product, or a price edit could affect any of
     * these four lists, and chasing every one of those call sites just
     * to invalidate a homepage teaser card isn't worth it - the actual
     * authoritative price/stock is always re-checked live at the
     * product detail page and at add-to-cart/checkout, so a homepage
     * card being up to 5 minutes stale is a fully acceptable tradeoff.
     */
    private const HOME_PRODUCTS_CACHE_TTL = 300;

    public function index(Request $request): void
    {
        $this->view('customer/home/index', [
            'pageTitle' => 'Kymera Collection - Luxury Redefined',
            'metaDescription' => 'Kymera Collection - a luxury destination for fashion, watches, jewelry, perfumes, bags and accessories.',
            'featuredCategories' => Category::activeOrdered(),
            'newArrivals' => Cache::remember('home.new_arrivals', self::HOME_PRODUCTS_CACHE_TTL, static fn (): array => Product::newArrivals(8)),
            'trending' => Cache::remember('home.trending', self::HOME_PRODUCTS_CACHE_TTL, static fn (): array => Product::trending(8)),
            'onSale' => Cache::remember('home.on_sale', self::HOME_PRODUCTS_CACHE_TTL, static fn (): array => Product::onSale(8)),
            'featuredProducts' => Cache::remember('home.featured', self::HOME_PRODUCTS_CACHE_TTL, static fn (): array => Product::featured(8)),
            'testimonials' => Testimonial::activeOrdered(),
        ], 'customer/layouts/site');
    }

    public function health(Request $request): void
    {
        $this->json(['status' => 'ok', 'app' => 'Kymera Collection']);
    }
}
