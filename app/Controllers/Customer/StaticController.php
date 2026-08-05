<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ContactMessage;

final class StaticController extends Controller
{
    public function about(Request $request): void
    {
        $this->view('customer/static/about', [
            'pageTitle' => 'About Us | Kymera Collection',
        ], 'customer/layouts/site');
    }

    public function showContact(Request $request): void
    {
        $this->view('customer/static/contact', [
            'pageTitle' => 'Contact Us | Kymera Collection',
        ], 'customer/layouts/site');
    }

    public function submitContact(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'name' => 'required|max:150',
            'email' => 'required|email|max:191',
            'message' => 'required|max:2000',
        ]);

        ContactMessage::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'subject' => self::nullable($request->input('subject')),
            'message' => $data['message'],
        ]);

        Session::flash('success', 'Thank you for reaching out. Our team will respond within 1-2 business days.');
        $this->redirect('/contact');
    }

    public function faqs(Request $request): void
    {
        $this->view('customer/static/faqs', [
            'pageTitle' => 'FAQs | Kymera Collection',
        ], 'customer/layouts/site');
    }

    public function privacy(Request $request): void
    {
        $this->view('customer/static/privacy', [
            'pageTitle' => 'Privacy Policy | Kymera Collection',
        ], 'customer/layouts/site');
    }

    public function terms(Request $request): void
    {
        $this->view('customer/static/terms', [
            'pageTitle' => 'Terms of Service | Kymera Collection',
        ], 'customer/layouts/site');
    }

    public function shippingReturns(Request $request): void
    {
        $this->view('customer/static/shipping-returns', [
            'pageTitle' => 'Shipping & Returns | Kymera Collection',
        ], 'customer/layouts/site');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
