<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;

final class DashboardController extends Controller
{
    /**
     * The dashboard's 17 separate aggregate queries (today's/monthly/
     * all-time sales and revenue, order/customer counts, best sellers,
     * low stock, a 14-day trend, a status breakdown, ...) are cached
     * as one bundle, not query-by-query - simpler than 17 separate
     * cache entries, and there's no single admin action that could
     * invalidate all of them anyway (a new order, a payment, a stock
     * adjustment, and a new customer signup each touch a different
     * subset). A 5-minute TTL is the tradeoff: admins get a dashboard
     * that's accurate as of a few minutes ago instead of hammering the
     * database on every page load/refresh, which is the norm for a
     * summary dashboard rather than a live operations view - nothing
     * here is a number an admin acts on with sub-minute urgency.
     */
    private const DASHBOARD_CACHE_TTL = 300;

    public function index(Request $request): void
    {
        $admin = Auth::user();
        $role = $admin !== null ? Role::find((int) $admin['role_id']) : null;

        $today = date('Y-m-d');
        $year = (int) date('Y');
        $month = (int) date('n');

        $metrics = Cache::remember(
            "admin.dashboard.{$today}",
            self::DASHBOARD_CACHE_TTL,
            static function () use ($today, $year, $month): array {
                $monthlySales = Order::sumSalesForMonth($year, $month);
                $monthlyRevenue = Order::sumPaidRevenueForMonth($year, $month);
                $monthlyExpenses = Expense::sumForMonth($year, $month);

                return [
                    'todaySales' => Order::sumSalesForDate($today),
                    'todayOrderCount' => Order::countForDate($today),
                    'monthlySales' => $monthlySales,
                    'monthlyRevenue' => $monthlyRevenue,
                    'allTimeRevenue' => Order::sumPaidRevenueAllTime(),
                    'monthlyExpenses' => $monthlyExpenses,
                    'monthlyProfit' => $monthlyRevenue - $monthlyExpenses,
                    'orderCount' => Order::countAllTime(),
                    'customerCount' => User::countCustomers(),
                    'newCustomersThisMonth' => User::countNewCustomersForMonth($year, $month),
                    'bestSellingProducts' => OrderItem::bestSelling(5),
                    'lowStockProducts' => Product::lowStock(8),
                    'latestOrders' => Order::latest(5),
                    'recentCustomers' => User::recentCustomers(5),
                    'salesTrend' => Order::dailySalesTrend(14),
                    'statusBreakdown' => Order::statusBreakdown(),
                ];
            }
        );

        $this->view('admin/dashboard/index', array_merge($metrics, [
            'pageTitle' => 'Admin Dashboard | Kymera Collection',
            'roleName' => $role['name'] ?? 'Staff',
        ]), 'admin/layouts/app');
    }
}
