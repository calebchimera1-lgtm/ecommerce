<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Vendor;
use App\Services\Upload\ImageUploader;
use RuntimeException;

final class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        $this->view('vendor/profile/index', [
            'pageTitle' => 'Store Profile | Kymera Collection Vendor Portal',
            'vendor' => $vendor,
        ], 'vendor/layouts/app');
    }

    public function update(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        $data = $this->validate($request->all(), [
            'store_name' => 'required|max:150',
        ]);

        try {
            $logo = ImageUploader::store($request->file('logo') ?? [], 'vendors');
        } catch (RuntimeException $e) {
            Session::flash('errors', ['logo' => [$e->getMessage()]]);
            $this->back();
        }

        if ($logo !== null && $vendor['logo'] !== null) {
            ImageUploader::delete($vendor['logo']);
        }

        $slug = $vendor['slug'];

        if (mb_strtolower($data['store_name']) !== mb_strtolower($vendor['store_name'])) {
            $slug = Vendor::generateSlug($data['store_name'], (int) $vendor['id']);
        }

        Vendor::update((int) $vendor['id'], [
            'store_name' => $data['store_name'],
            'slug' => $slug,
            'description' => self::nullable($request->input('description')),
            'phone' => self::nullable($request->input('phone')),
            'business_registration_number' => self::nullable($request->input('business_registration_number')),
            'payout_details' => self::nullable($request->input('payout_details')),
            'logo' => $logo ?? $vendor['logo'],
        ]);

        Session::flash('success', 'Store profile updated.');
        $this->redirect('/vendor/profile');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
