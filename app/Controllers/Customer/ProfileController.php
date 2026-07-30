<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Notification\Mailer;

final class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();

        $this->view('customer/account/profile/index', [
            'pageTitle' => 'My Profile | Kymera Collection',
            'user' => User::find($userId),
            'addresses' => UserAddress::forUser($userId),
        ], 'customer/layouts/site');
    }

    public function updateProfile(Request $request): void
    {
        $userId = (int) Auth::id();
        $user = User::find($userId);

        $data = $this->validate($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:191',
        ]);

        $email = mb_strtolower($data['email']);
        $existing = User::findByEmail($email);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            Session::flash('errors', ['email' => ['That email address is already in use.']]);
            $this->back();
        }

        $updates = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => self::nullable($request->input('phone')),
        ];

        $emailChanged = $email !== mb_strtolower($user['email']);

        if ($emailChanged) {
            $token = bin2hex(random_bytes(32));
            $updates['email'] = $email;
            $updates['email_verified_at'] = null;
            $updates['email_verification_token'] = hash('sha256', $token);

            Mailer::send($email, 'Verify your new email address', 'verify-email', [
                'name' => $data['first_name'],
                'verifyUrl' => url('/verify-email/' . $token) . '?email=' . urlencode($email),
            ]);
        }

        User::update($userId, $updates);

        Session::flash(
            'success',
            $emailChanged
                ? 'Profile updated. Please check your new email address to verify it.'
                : 'Profile updated.'
        );
        $this->redirect('/account/profile');
    }

    public function updatePassword(Request $request): void
    {
        $userId = (int) Auth::id();
        $user = User::find($userId);

        $data = $this->validate($request->all(), [
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!password_verify($data['current_password'], $user['password_hash'])) {
            Session::flash('errors', ['current_password' => ['Your current password is incorrect.']]);
            $this->back();
        }

        User::update($userId, [
            'password_hash' => password_hash($data['password'], config('security.hash_algo')),
            'remember_token' => null,
        ]);

        Session::flash('success', 'Password updated.');
        $this->redirect('/account/profile');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
