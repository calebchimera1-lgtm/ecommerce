<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Testimonial;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/home/index', [
            'pageTitle' => 'Kymera Collection - Luxury Redefined',
            'metaDescription' => 'Kymera Collection - a luxury destination for fashion, watches, jewelry, perfumes, bags and accessories.',
            'featuredCategories' => Category::activeOrdered(),
            'newArrivals' => Product::newArrivals(8),
            'trending' => Product::trending(8),
            'onSale' => Product::onSale(8),
            'featuredProducts' => Product::featured(8),
            'testimonials' => Testimonial::activeOrdered(),
        ], 'customer/layouts/site');
    }

    public function health(Request $request): void
    {
        $this->json(['status' => 'ok', 'app' => 'Kymera Collection']);
    }
}
