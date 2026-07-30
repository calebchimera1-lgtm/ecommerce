<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('admin/auth/login', ['pageTitle' => 'Staff Sign In | Kymera Collection'], 'admin/layouts/login');
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = mb_strtolower($data['email']);
        // Prefixed so admin login throttling never interferes with a
        // customer login attempt against the same email address.
        $identifier = 'admin:' . $email;
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

        if (!Auth::roleCan((int) $user['role_id'], 'dashboard.view')) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['You do not have access to the admin panel.']]);
            $this->back();
        }

        RateLimiter::hit($identifier, $ip, true);
        Auth::login($user);
        User::update($user['id'], ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip]);

        $this->redirect('/admin/dashboard');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        $this->redirect('/admin/login');
    }
}
