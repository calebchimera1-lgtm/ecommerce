<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Uuid;
use App\Models\Cart;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use App\Services\Notification\Mailer;

final class AuthController extends Controller
{
    private const LAYOUT = 'customer/layouts/auth';

    public function showRegister(Request $request): void
    {
        $this->view('customer/auth/register', ['pageTitle' => 'Create Account | Kymera Collection'], self::LAYOUT);
    }

    public function register(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $customerRole = Role::findBy('slug', 'customer');

        if ($customerRole === null) {
            Session::flash('errors', ['email' => ['Registration is temporarily unavailable. Please try again later.']]);
            $this->back();
        }

        $email = mb_strtolower($data['email']);
        $verificationToken = bin2hex(random_bytes(32));

        User::create([
            'uuid' => Uuid::v4(),
            'role_id' => $customerRole['id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'password_hash' => password_hash($data['password'], config('security.hash_algo')),
            'email_verification_token' => hash('sha256', $verificationToken),
        ]);

        Mailer::send($email, 'Verify your Kymera Collection account', 'verify-email', [
            'name' => $data['first_name'],
            'verifyUrl' => url('/verify-email/' . $verificationToken) . '?email=' . urlencode($email),
        ]);

        Session::flash('success', 'Account created. Please check your email to verify your account before logging in.');
        $this->redirect('/login');
    }

    public function showLogin(Request $request): void
    {
        $this->view('customer/auth/login', ['pageTitle' => 'Sign In | Kymera Collection'], self::LAYOUT);
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $identifier = mb_strtolower($data['email']);
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts($identifier)) {
            Session::flash('errors', ['email' => ['Too many failed login attempts. Please try again in a few minutes.']]);
            $this->back();
        }

        $user = User::findByEmail($identifier);

        if ($user === null || !password_verify($data['password'], $user['password_hash'])) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['Those credentials do not match our records.']]);
            $this->back();
        }

        if ($user['status'] !== 'active') {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['This account is not active. Please contact support.']]);
            $this->back();
        }

        if ($user['email_verified_at'] === null) {
            RateLimiter::hit($identifier, $ip, false);
            Session::flash('errors', ['email' => ['Please verify your email address before logging in.']]);
            $this->back();
        }

        RateLimiter::hit($identifier, $ip, true);
        // Capture the pre-login session id before Auth::login() regenerates
        // it, so any guest cart tied to this session can still be found.
        $guestSessionId = session_id();
        Auth::login($user, remember: $request->input('remember') !== null);
        User::update($user['id'], ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip]);
        Cart::mergeSessionCartIntoUser($guestSessionId, (int) $user['id']);

        $this->redirect('/account');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        $this->redirect('/');
    }

    public function showForgotPassword(Request $request): void
    {
        $this->view('customer/auth/forgot-password', ['pageTitle' => 'Forgot Password | Kymera Collection'], self::LAYOUT);
    }

    public function forgotPassword(Request $request): void
    {
        $data = $this->validate($request->all(), ['email' => 'required|email']);
        $email = mb_strtolower($data['email']);
        $user = User::findByEmail($email);

        if ($user !== null) {
            PasswordReset::invalidateFor($email);
            $token = bin2hex(random_bytes(32));
            PasswordReset::issue($email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600));

            Mailer::send($email, 'Reset your Kymera Collection password', 'reset-password', [
                'name' => $user['first_name'],
                'resetUrl' => url('/reset-password/' . $token) . '?email=' . urlencode($email),
            ]);
        }

        // Always show the same message whether or not the email exists,
        // to avoid leaking which addresses are registered.
        Session::flash('success', 'If an account exists for that email, a password reset link has been sent.');
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(Request $request): void
    {
        $token = (string) $request->route('token');
        $email = mb_strtolower((string) $request->query('email', ''));

        if ($email === '' || PasswordReset::findValidToken($email, hash('sha256', $token)) === null) {
            Session::flash('errors', ['email' => ['This password reset link is invalid or has expired.']]);
            $this->redirect('/forgot-password');
        }

        $this->view('customer/auth/reset-password', [
            'pageTitle' => 'Reset Password | Kymera Collection',
            'token' => $token,
            'email' => $email,
        ], self::LAYOUT);
    }

    public function resetPassword(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $email = mb_strtolower($data['email']);
        $record = PasswordReset::findValidToken($email, hash('sha256', $data['token']));

        if ($record === null) {
            Session::flash('errors', ['email' => ['This password reset link is invalid or has expired.']]);
            $this->redirect('/forgot-password');
        }

        $user = User::findByEmail($email);

        if ($user === null) {
            Session::flash('errors', ['email' => ['We could not find an account for that email.']]);
            $this->redirect('/forgot-password');
        }

        User::update($user['id'], [
            'password_hash' => password_hash($data['password'], config('security.hash_algo')),
            'remember_token' => null,
        ]);

        PasswordReset::invalidateFor($email);

        Session::flash('success', 'Your password has been reset. Please log in.');
        $this->redirect('/login');
    }

    public function verifyEmail(Request $request): void
    {
        $token = (string) $request->route('token');
        $email = mb_strtolower((string) $request->query('email', ''));
        $hashedToken = hash('sha256', $token);

        $user = $email !== '' ? User::findByEmail($email) : null;

        if (
            $user === null
            || $user['email_verification_token'] === null
            || !hash_equals($user['email_verification_token'], $hashedToken)
        ) {
            Session::flash('errors', ['email' => ['This verification link is invalid or has already been used.']]);
            $this->redirect('/login');
        }

        User::update($user['id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
        ]);

        Session::flash('success', 'Your email has been verified. You can now log in.');
        $this->redirect('/login');
    }

    public function resendVerification(Request $request): void
    {
        $data = $this->validate($request->all(), ['email' => 'required|email']);
        $email = mb_strtolower($data['email']);
        $user = User::findByEmail($email);

        if ($user !== null && $user['email_verified_at'] === null) {
            $token = bin2hex(random_bytes(32));
            User::update($user['id'], ['email_verification_token' => hash('sha256', $token)]);

            Mailer::send($email, 'Verify your Kymera Collection account', 'verify-email', [
                'name' => $user['first_name'],
                'verifyUrl' => url('/verify-email/' . $token) . '?email=' . urlencode($email),
            ]);
        }

        Session::flash('success', 'If that account needs verification, a new link has been sent.');
        $this->redirect('/login');
    }
}
