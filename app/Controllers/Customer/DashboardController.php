<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;

/**
 * Minimal placeholder for the authenticated customer area. The full
 * dashboard (orders, addresses, wishlist, reviews) is built out in the
 * Customer Dashboard module; this proves the login/auth-middleware
 * flow end-to-end in the meantime.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/account/dashboard', [
            'pageTitle' => 'My Account | Kymera Collection',
            'user' => Auth::user(),
        ]);
    }
}
