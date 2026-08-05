<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Export\CsvExporter;
use App\Services\Export\ExcelExporter;
use App\Services\Export\PdfExporter;

final class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/reports/index', [
            'pageTitle' => 'Reports | Kymera Collection Admin',
        ], 'admin/layouts/app');
    }

    public function sales(Request $request): void
    {
        [$startDate, $endDate] = self::dateRange($request);
        $summary = Order::salesSummary($startDate, $endDate);
        $daily = Order::salesReportDaily($startDate, $endDate);

        $format = (string) $request->query('format', '');

        if ($format !== '') {
            $headers = ['Date', 'Orders', 'Sales', 'Revenue'];
            $rows = array_map(static fn (array $d): array => [
                $d['day'], $d['order_count'], number_format($d['sales_total'], 2), number_format($d['revenue_total'], 2),
            ], $daily);

            self::export($format, 'sales-report', 'Sales Report', $headers, $rows, [
                sprintf('Range: %s to %s', $startDate, $endDate),
                sprintf('Orders: %d | Sales: %s | Revenue: %s | Avg Order Value: %s',
                    $summary['order_count'], money($summary['sales_total']), money($summary['revenue_total']), money($summary['avg_order_value'])),
            ]);
        }

        $this->view('admin/reports/sales', [
            'pageTitle' => 'Sales Report | Kymera Collection Admin',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $summary,
            'daily' => $daily,
        ], 'admin/layouts/app');
    }

    public function inventory(Request $request): void
    {
        $rows = Product::inventoryReportRows();
        $totalValue = array_sum(array_map(static fn (array $r): float => (float) $r['stock_value'], $rows));

        $format = (string) $request->query('format', '');

        if ($format !== '') {
            $headers = ['SKU', 'Product', 'Category', 'Supplier', 'Stock', 'Low Stock Threshold', 'Cost Price', 'Stock Value'];
            $exportRows = array_map(static fn (array $r): array => [
                $r['sku'], $r['name'], $r['category_name'] ?? '', $r['supplier_name'] ?? '',
                $r['stock_quantity'], $r['low_stock_threshold'], number_format((float) $r['cost_price'], 2), number_format((float) $r['stock_value'], 2),
            ], $rows);

            self::export($format, 'inventory-report', 'Inventory Report', $headers, $exportRows, [
                sprintf('Products: %d | Total Stock Value: %s', count($rows), money($totalValue)),
            ]);
        }

        $this->view('admin/reports/inventory', [
            'pageTitle' => 'Inventory Report | Kymera Collection Admin',
            'rows' => $rows,
            'totalValue' => $totalValue,
        ], 'admin/layouts/app');
    }

    public function customers(Request $request): void
    {
        [$startDate, $endDate] = self::dateRange($request);
        $rows = User::topCustomers($startDate, $endDate);

        $format = (string) $request->query('format', '');

        if ($format !== '') {
            $headers = ['Customer', 'Email', 'Orders', 'Total Spent', 'Last Order'];
            $exportRows = array_map(static fn (array $r): array => [
                trim($r['first_name'] . ' ' . $r['last_name']), $r['email'], $r['order_count'],
                number_format((float) $r['total_spent'], 2), $r['last_order_at'],
            ], $rows);

            self::export($format, 'customer-report', 'Customer Report', $headers, $exportRows, [
                sprintf('Range: %s to %s | Customers with orders: %d', $startDate, $endDate, count($rows)),
            ]);
        }

        $this->view('admin/reports/customers', [
            'pageTitle' => 'Customer Report | Kymera Collection Admin',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rows' => $rows,
        ], 'admin/layouts/app');
    }

    public function products(Request $request): void
    {
        [$startDate, $endDate] = self::dateRange($request);
        $rows = OrderItem::bestSellingBetween($startDate, $endDate);

        $format = (string) $request->query('format', '');

        if ($format !== '') {
            $headers = ['Product', 'SKU', 'Units Sold', 'Revenue'];
            $exportRows = array_map(static fn (array $r): array => [
                $r['name'], $r['sku'], $r['units_sold'], number_format((float) $r['revenue'], 2),
            ], $rows);

            self::export($format, 'product-performance-report', 'Product Performance Report', $headers, $exportRows, [
                sprintf('Range: %s to %s | Products sold: %d', $startDate, $endDate, count($rows)),
            ]);
        }

        $this->view('admin/reports/products', [
            'pageTitle' => 'Product Performance Report | Kymera Collection Admin',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rows' => $rows,
        ], 'admin/layouts/app');
    }

    /**
     * @return array{0:string,1:string} [startDate, endDate] as Y-m-d,
     * defaulting to the current calendar month and clamped to a
     * 366-day span so a fat-fingered year doesn't turn a report query
     * into an unbounded table scan.
     */
    private static function dateRange(Request $request): array
    {
        $startDate = (string) $request->query('start_date', date('Y-m-01'));
        $endDate = (string) $request->query('end_date', date('Y-m-d'));

        if (strtotime($startDate) === false) {
            $startDate = date('Y-m-01');
        }

        if (strtotime($endDate) === false) {
            $endDate = date('Y-m-d');
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        if (strtotime($endDate) - strtotime($startDate) > 366 * 86400) {
            $endDate = date('Y-m-d', strtotime($startDate) + 366 * 86400);
        }

        return [$startDate, $endDate];
    }

    private static function export(string $format, string $filenameBase, string $title, array $headers, array $rows, array $summaryLines = []): never
    {
        match ($format) {
            'csv' => CsvExporter::stream($filenameBase . '.csv', $headers, $rows),
            'excel' => ExcelExporter::stream($filenameBase . '.xls', $title, $headers, $rows),
            'pdf' => PdfExporter::stream($filenameBase . '.pdf', $title, $headers, $rows, $summaryLines),
            default => \App\Core\Response::abort(400, 'Unknown export format.'),
        };
    }
}
