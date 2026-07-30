<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Coupon;
use PDOException;

final class CouponController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/coupons/index', [
            'pageTitle' => 'Coupons | Kymera Collection Admin',
            'coupons' => Coupon::all('created_at', 'DESC'),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/coupons/form', [
            'pageTitle' => 'New Coupon | Kymera Collection Admin',
            'coupon' => null,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'code' => 'required|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric',
        ]);

        $code = mb_strtoupper(trim($data['code']));

        if (Coupon::findBy('code', $code) !== null) {
            Session::flash('errors', ['code' => ['This coupon code is already in use.']]);
            $this->back();
        }

        Coupon::create([
            'code' => $code,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => self::nullableDecimal($request->input('min_order_amount')),
            'max_discount_amount' => self::nullableDecimal($request->input('max_discount_amount')),
            'usage_limit' => self::nullableInt($request->input('usage_limit')),
            'per_user_limit' => self::nullableInt($request->input('per_user_limit')),
            'starts_at' => self::nullableDatetime($request->input('starts_at')),
            'expires_at' => self::nullableDatetime($request->input('expires_at')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
        ]);

        Session::flash('success', 'Coupon created.');
        $this->redirect('/admin/coupons');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->route('id');
        $coupon = Coupon::find($id);

        if ($coupon === null) {
            Response::abort(404, 'Coupon not found.');
        }

        $this->view('admin/coupons/form', [
            'pageTitle' => 'Edit Coupon | Kymera Collection Admin',
            'coupon' => $coupon,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $coupon = Coupon::find($id);

        if ($coupon === null) {
            Response::abort(404, 'Coupon not found.');
        }

        $data = $this->validate($request->all(), [
            'code' => 'required|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric',
        ]);

        $code = mb_strtoupper(trim($data['code']));
        $existing = Coupon::findBy('code', $code);

        if ($existing !== null && (int) $existing['id'] !== $id) {
            Session::flash('errors', ['code' => ['This coupon code is already in use.']]);
            $this->back();
        }

        Coupon::update($id, [
            'code' => $code,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => self::nullableDecimal($request->input('min_order_amount')),
            'max_discount_amount' => self::nullableDecimal($request->input('max_discount_amount')),
            'usage_limit' => self::nullableInt($request->input('usage_limit')),
            'per_user_limit' => self::nullableInt($request->input('per_user_limit')),
            'starts_at' => self::nullableDatetime($request->input('starts_at')),
            'expires_at' => self::nullableDatetime($request->input('expires_at')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
        ]);

        Session::flash('success', 'Coupon updated.');
        $this->redirect('/admin/coupons');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');

        try {
            Coupon::delete($id);
            Session::flash('success', 'Coupon deleted.');
        } catch (PDOException) {
            Session::flash('errors', ['coupon' => ['This coupon could not be deleted.']]);
        }

        $this->redirect('/admin/coupons');
    }

    private static function nullableDecimal(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }

    private static function nullableDatetime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // <input type="datetime-local"> submits "YYYY-MM-DDTHH:MM".
        $normalized = str_replace('T', ' ', $value);

        return strlen($normalized) === 16 ? $normalized . ':00' : $normalized;
    }
}
