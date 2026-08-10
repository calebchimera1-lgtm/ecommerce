<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * A vendor's own analytics dashboard - the vendor-side counterpart to
 * Admin\DashboardController (Module 9), same shape (stat tiles, a
 * sales trend chart, a status breakdown chart, a best-sellers table)
 * but every figure scoped to this one vendor's own vendor_orders and
 * order_items, never platform-wide totals or another vendor's data.
 *
 * Kept separate from Vendor\DashboardController - the landing page
 * (Modules 15-18) stays a lightweight "here's what needs attention"
 * view; this is the deeper "how is my store actually doing" view, the
 * same split Module 9's rich admin dashboard and the later Reports
 * section (Admin\ReportController) have on the admin side.
 */
final class AnalyticsController extends Controller
{
    public function index(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());
        $vendorId = (int) $vendor['id'];

        $today = date('Y-m-d');
        $year = (int) date('Y');
        $month = (int) date('n');

        $this->view('vendor/analytics/index', [
            'pageTitle' => 'Analytics | Kymera Collection Vendor Portal',
            'vendor' => $vendor,
            'todaySales' => VendorOrder::sumSubtotalForDate($vendorId, $today),
            'todayOrderCount' => VendorOrder::countForDate($vendorId, $today),
            'monthlySales' => VendorOrder::sumSubtotalForMonth($vendorId, $year, $month),
            'monthlyPayout' => VendorOrder::sumPayoutForMonth($vendorId, $year, $month),
            'allTimeSales' => VendorOrder::sumSubtotalAllTime($vendorId),
            'unpaidBalance' => VendorOrder::unpaidBalanceForVendor($vendorId),
            'orderCount' => VendorOrder::countForVendor($vendorId),
            'salesTrend' => VendorOrder::dailySalesTrend($vendorId, 14),
            'statusBreakdown' => VendorOrder::statusBreakdown($vendorId),
            'bestSellingProducts' => OrderItem::bestSellingForVendor($vendorId, 5),
        ], 'vendor/layouts/app');
    }
}
