<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('vendor/auth/login', ['pageTitle' => 'Vendor Sign In | Kymera Collection'], 'vendor/layouts/login');
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = mb_strtolower($data['email']);
        // Prefixed so vendor login throttling never interferes with a
        // customer or admin login attempt against the same email.
        $identifier = 'vendor:' . $email;
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts($identifier)) {
            Session::flash('errors', ['email' => ['Too many failed login attempts. Please try again in a few minutes.']]);
            $this->back();
        }

        $user = User::findByEmail($email);

        if ($user === null || !password_verify($data['password'], $user['password_hash'])) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['Those credentials do not match our records.']]);
            $this->back();
        }

        if ($user['status'] !== 'active') {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['This account is not active.']]);
            $this->back();
        }

        $role = Role::find((int) $user['role_id']);

        if ($role === null || $role['slug'] !== 'vendor') {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['This login is for vendor accounts only.']]);
            $this->back();
        }

        $vendor = Vendor::findByUserId((int) $user['id']);

        if ($vendor === null) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['No vendor profile is linked to this account. Please contact support.']]);
            $this->back();
        }

        $message = match ($vendor['status']) {
            'pending' => 'Your vendor application is still under review. You will be notified once it is approved.',
            'rejected' => 'Your vendor application was not approved.' . ($vendor['rejection_reason'] !== null ? ' Reason: ' . $vendor['rejection_reason'] : ''),
            'suspended' => 'Your vendor account has been suspended. Please contact support.',
            default => null,
        };

        if ($message !== null) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => [$message]]);
            $this->back();
        }

        RateLimiter::hit($identifier, $ip, true);
        Auth::login($user);
        User::update($user['id'], ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip]);

        $this->redirect('/vendor/dashboard');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        $this->redirect('/vendor/login');
    }
}
