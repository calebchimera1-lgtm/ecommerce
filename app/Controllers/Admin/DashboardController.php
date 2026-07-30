<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
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
    public function index(Request $request): void
    {
        $admin = Auth::user();
        $role = $admin !== null ? Role::find((int) $admin['role_id']) : null;

        $today = date('Y-m-d');
        $year = (int) date('Y');
        $month = (int) date('n');

        $monthlySales = Order::sumSalesForMonth($year, $month);
        $monthlyRevenue = Order::sumPaidRevenueForMonth($year, $month);
        $monthlyExpenses = Expense::sumForMonth($year, $month);

        $this->view('admin/dashboard/index', [
            'pageTitle' => 'Admin Dashboard | Kymera Collection',
            'roleName' => $role['name'] ?? 'Staff',
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
        ], 'admin/layouts/app');
    }
}
