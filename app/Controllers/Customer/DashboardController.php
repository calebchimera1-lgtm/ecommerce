<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\Wishlist;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();
        $orders = Order::forUser($userId);

        $this->view('customer/account/dashboard', [
            'pageTitle' => 'My Account | Kymera Collection',
            'user' => Auth::user(),
            'recentOrders' => array_slice($orders, 0, 5),
            'orderCount' => count($orders),
            'wishlistCount' => Wishlist::countForUser($userId),
            'reviewCount' => count(ProductReview::forUser($userId)),
        ], 'customer/layouts/site');
    }
}
