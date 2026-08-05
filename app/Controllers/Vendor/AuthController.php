<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Uuid;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('vendor/auth/login', ['pageTitle' => 'Vendor Sign In | Kymera Collection'], 'vendor/layouts/login');
    }

    public function showRegister(Request $request): void
    {
        $this->view('vendor/auth/register', ['pageTitle' => 'Become a Vendor | Kymera Collection'], 'vendor/layouts/login');
    }

    public function register(Request $request): void
    {
        $ip = $request->ip();
        $identifier = 'vendor-register:' . $ip;

        if (RateLimiter::tooManyAttempts($identifier)) {
            Session::flash('errors', ['email' => ['Too many application attempts from this location. Please try again in a few minutes.']]);
            $this->back();
        }

        // Counted before validation for the same reason customer
        // registration's rate limit is (Module 14): this caps request
        // frequency per IP regardless of whether the submission turns
        // out valid, not just successful signups.
        RateLimiter::hit($identifier, $ip, false);

        $data = $this->validate($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'store_name' => 'required|max:150',
        ]);

        $vendorRole = Role::findBy('slug', 'vendor');

        if ($vendorRole === null) {
            Session::flash('errors', ['email' => ['Vendor applications are temporarily unavailable. Please try again later.']]);
            $this->back();
        }

        $email = mb_strtolower($data['email']);

        $userId = User::create([
            'uuid' => Uuid::v4(),
            'role_id' => $vendorRole['id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'password_hash' => password_hash($data['password'], config('security.hash_algo')),
            // Vendor accounts skip the customer email-verification
            // flow - the admin approval step (Module 15/16) already
            // requires a human to review the application before the
            // account can do anything, which covers the same "is this
            // a real, reachable applicant" concern a verification
            // email would, without stacking two review gates.
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        Vendor::create([
            'user_id' => $userId,
            'store_name' => $data['store_name'],
            'slug' => Vendor::generateSlug($data['store_name']),
            'description' => self::nullable($request->input('description')),
            'phone' => self::nullable($request->input('phone')),
            'business_registration_number' => self::nullable($request->input('business_registration_number')),
            'status' => 'pending',
        ]);

        Session::flash('success', 'Thank you for applying to sell on Kymera Collection. We will review your application and email you once a decision has been made.');
        $this->redirect('/vendor/login');
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

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
