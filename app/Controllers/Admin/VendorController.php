<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Vendor;
use App\Services\Notification\Mailer;

final class VendorController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'status' => (string) $request->query('status', ''),
            'search' => trim((string) $request->query('search', '')),
        ];

        $vendors = Vendor::paginateAdmin($page, self::PER_PAGE, $filters);

        foreach ($vendors as &$vendor) {
            $vendor['tier'] = Vendor::tierLabel((int) $vendor['review_count'], (float) $vendor['average_rating']);
        }
        unset($vendor);

        $this->view('admin/vendors/index', [
            'pageTitle' => 'Vendors | Kymera Collection Admin',
            'vendors' => $vendors,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Vendor::countAdmin($filters),
        ], 'admin/layouts/app');
    }

    public function show(Request $request): void
    {
        $vendor = $this->loadVendor($request);
        $tier = Vendor::tierLabel((int) $vendor['review_count'], (float) $vendor['average_rating']);

        $this->view('admin/vendors/show', [
            'pageTitle' => $vendor['store_name'] . ' | Kymera Collection Admin',
            'vendor' => $vendor,
            'tier' => $tier,
        ], 'admin/layouts/app');
    }

    public function approve(Request $request): void
    {
        $vendor = $this->loadVendor($request);

        if (!in_array($vendor['status'], ['pending', 'rejected'], true)) {
            Session::flash('errors', ['status' => ['Only pending or rejected applications can be approved.']]);
            $this->redirect('/admin/vendors/' . (int) $vendor['id']);
        }

        Vendor::approve((int) $vendor['id'], (int) Auth::id());

        AuditLog::record(Auth::id(), 'vendor.approved', 'vendor', (int) $vendor['id'], [
            'status' => $vendor['status'],
        ], ['status' => 'approved']);

        Mailer::send($vendor['email'], 'Your Kymera Collection vendor application was approved', 'vendor-approved', [
            'name' => $vendor['first_name'],
            'storeName' => $vendor['store_name'],
            'loginUrl' => url('/vendor/login'),
        ]);

        Session::flash('success', 'Vendor approved.');
        $this->redirect('/admin/vendors/' . (int) $vendor['id']);
    }

    public function reject(Request $request): void
    {
        $vendor = $this->loadVendor($request);

        if ($vendor['status'] !== 'pending') {
            Session::flash('errors', ['status' => ['Only pending applications can be rejected.']]);
            $this->redirect('/admin/vendors/' . (int) $vendor['id']);
        }

        $data = $this->validate($request->all(), ['rejection_reason' => 'required|max:255']);

        Vendor::reject((int) $vendor['id'], $data['rejection_reason']);

        AuditLog::record(Auth::id(), 'vendor.rejected', 'vendor', (int) $vendor['id'], [
            'status' => $vendor['status'],
        ], ['status' => 'rejected', 'reason' => $data['rejection_reason']]);

        Mailer::send($vendor['email'], 'Your Kymera Collection vendor application', 'vendor-rejected', [
            'name' => $vendor['first_name'],
            'storeName' => $vendor['store_name'],
            'reason' => $data['rejection_reason'],
        ]);

        Session::flash('success', 'Vendor application rejected.');
        $this->redirect('/admin/vendors/' . (int) $vendor['id']);
    }

    public function suspend(Request $request): void
    {
        $vendor = $this->loadVendor($request);

        if ($vendor['status'] !== 'approved') {
            Session::flash('errors', ['status' => ['Only approved vendors can be suspended.']]);
            $this->redirect('/admin/vendors/' . (int) $vendor['id']);
        }

        Vendor::suspend((int) $vendor['id']);

        AuditLog::record(Auth::id(), 'vendor.suspended', 'vendor', (int) $vendor['id'], [
            'status' => $vendor['status'],
        ], ['status' => 'suspended']);

        Mailer::send($vendor['email'], 'Your Kymera Collection vendor account has been suspended', 'vendor-suspended', [
            'name' => $vendor['first_name'],
            'storeName' => $vendor['store_name'],
        ]);

        Session::flash('success', 'Vendor suspended.');
        $this->redirect('/admin/vendors/' . (int) $vendor['id']);
    }

    public function reactivate(Request $request): void
    {
        $vendor = $this->loadVendor($request);

        if ($vendor['status'] !== 'suspended') {
            Session::flash('errors', ['status' => ['Only suspended vendors can be reactivated.']]);
            $this->redirect('/admin/vendors/' . (int) $vendor['id']);
        }

        Vendor::reactivate((int) $vendor['id']);

        AuditLog::record(Auth::id(), 'vendor.reactivated', 'vendor', (int) $vendor['id'], [
            'status' => $vendor['status'],
        ], ['status' => 'approved']);

        Mailer::send($vendor['email'], 'Your Kymera Collection vendor account has been reinstated', 'vendor-approved', [
            'name' => $vendor['first_name'],
            'storeName' => $vendor['store_name'],
            'loginUrl' => url('/vendor/login'),
        ]);

        Session::flash('success', 'Vendor reactivated.');
        $this->redirect('/admin/vendors/' . (int) $vendor['id']);
    }

    private function loadVendor(Request $request): array
    {
        $id = (int) $request->route('id');
        $vendor = Vendor::findWithUser($id);

        if ($vendor === null) {
            Response::abort(404, 'Vendor not found.');
        }

        return $vendor;
    }
}
