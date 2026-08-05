<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Product;
use App\Models\Vendor;

/**
 * Vendor landing page. Module 15 shipped this as a placeholder before
 * there was anything vendor-specific to show; Module 17 gives it real
 * product-approval stats now that vendors have listings. Sales and
 * payout figures arrive in Module 18.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        $this->view('vendor/dashboard/index', [
            'pageTitle' => 'Vendor Dashboard | Kymera Collection',
            'vendor' => $vendor,
            'productCounts' => Product::statusCountsForVendor((int) $vendor['id']),
        ], 'vendor/layouts/app');
    }
}
