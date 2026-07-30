<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\UserAddress;

final class AddressController extends Controller
{
    public function store(Request $request): void
    {
        $userId = (int) Auth::id();

        $data = $this->validate($request->all(), [
            'full_name' => 'required|max:150',
            'phone' => 'required|max:30',
            'address_line1' => 'required|max:255',
            'city' => 'required|max:100',
            'country' => 'required|max:100',
        ]);

        $makeDefault = $request->input('is_default') !== null;

        if ($makeDefault) {
            UserAddress::clearDefault($userId);
        }

        UserAddress::create([
            'user_id' => $userId,
            'type' => $request->input('type') === 'billing' ? 'billing' : 'shipping',
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => self::nullable($request->input('address_line2')),
            'city' => $data['city'],
            'state' => self::nullable($request->input('state')),
            'postal_code' => self::nullable($request->input('postal_code')),
            'country' => $data['country'],
            'is_default' => $makeDefault ? 1 : 0,
        ]);

        Session::flash('success', 'Address added.');
        $this->redirect('/account/profile');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $userId = (int) Auth::id();

        if (!UserAddress::belongsToUser($id, $userId)) {
            Response::abort(404, 'Address not found.');
        }

        $data = $this->validate($request->all(), [
            'full_name' => 'required|max:150',
            'phone' => 'required|max:30',
            'address_line1' => 'required|max:255',
            'city' => 'required|max:100',
            'country' => 'required|max:100',
        ]);

        $makeDefault = $request->input('is_default') !== null;

        if ($makeDefault) {
            UserAddress::clearDefault($userId);
        }

        UserAddress::update($id, [
            'type' => $request->input('type') === 'billing' ? 'billing' : 'shipping',
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => self::nullable($request->input('address_line2')),
            'city' => $data['city'],
            'state' => self::nullable($request->input('state')),
            'postal_code' => self::nullable($request->input('postal_code')),
            'country' => $data['country'],
            'is_default' => $makeDefault ? 1 : 0,
        ]);

        Session::flash('success', 'Address updated.');
        $this->redirect('/account/profile');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');
        $userId = (int) Auth::id();

        if (UserAddress::belongsToUser($id, $userId)) {
            UserAddress::delete($id);
            Session::flash('success', 'Address removed.');
        }

        $this->redirect('/account/profile');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
