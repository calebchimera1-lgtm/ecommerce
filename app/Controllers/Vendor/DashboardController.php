<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * Vendor landing page. Module 15 shipped this as a placeholder before
 * there was anything vendor-specific to show; Module 17 added real
 * product-approval stats; Module 18 adds order and earnings figures
 * now that orders actually get split and commission gets tracked.
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
            'unpaidBalance' => VendorOrder::unpaidBalanceForVendor((int) $vendor['id']),
            'orderCount' => VendorOrder::countForVendor((int) $vendor['id']),
        ], 'vendor/layouts/app');
    }
}
