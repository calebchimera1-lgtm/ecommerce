<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Core\Logger;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentResult;

/**
 * M-Pesa via Safaricom's Daraja STK Push API. Unlike Stripe/PayPal,
 * this is inherently asynchronous: initiating the request only pushes
 * a payment prompt to the customer's phone - actual confirmation
 * arrives later via a callback Safaricom POSTs to MPESA_CALLBACK_URL,
 * which is NOT implemented in this module (it needs a public,
 * unauthenticated, signature-verified endpoint, which is meaningful
 * scope of its own and moot without a publicly reachable HTTPS URL in
 * this development environment). A successful charge() here means
 * "the prompt was sent," recorded as 'pending', not 'completed' - the
 * callback handler that flips it to 'paid' is a documented follow-up.
 */
final class MpesaGateway implements PaymentGatewayInterface
{
    public function slug(): string
    {
        return 'mpesa';
    }

    public function label(): string
    {
        return 'M-Pesa';
    }

    public function isConfigured(): bool
    {
        return (string) config('payment.mpesa.consumer_key') !== ''
            && (string) config('payment.mpesa.consumer_secret') !== ''
            && (string) config('payment.mpesa.shortcode') !== ''
            && (string) config('payment.mpesa.passkey') !== '';
    }

    /**
     * $input is expected to carry 'phone' - the customer's M-Pesa
     * phone number (format 2547XXXXXXXX).
     */
    public function charge(array $order, array $input): PaymentResult
    {
        if (!$this->isConfigured()) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                message: 'M-Pesa is not configured. Set MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_SHORTCODE, and MPESA_PASSKEY to enable it.'
            );
        }

        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));

        if ($phone === '' || $phone === null) {
            return new PaymentResult(success: false, status: 'failed', message: 'A valid M-Pesa phone number is required.');
        }

        $accessToken = $this->fetchAccessToken();

        if ($accessToken === null) {
            return new PaymentResult(success: false, status: 'failed', message: 'Could not authenticate with M-Pesa. Please try again.');
        }

        $shortcode = (string) config('payment.mpesa.shortcode');
        $passkey = (string) config('payment.mpesa.passkey');
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $body = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) ceil($order['total']),
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => (string) config('payment.mpesa.callback_url'),
            'AccountReference' => $order['order_number'],
            'TransactionDesc' => 'Kymera Collection order ' . $order['order_number'],
        ];

        $ch = curl_init($this->baseUrl() . '/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Logger::error('M-Pesa STK push transport error', ['order_number' => $order['order_number']]);

            return new PaymentResult(success: false, status: 'failed', message: 'Could not reach M-Pesa. Please try again.');
        }

        $payload = json_decode($response, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300 && ($payload['ResponseCode'] ?? null) === '0') {
            return new PaymentResult(
                success: true,
                status: 'pending',
                transactionId: (string) ($payload['CheckoutRequestID'] ?? ''),
                message: 'Check your phone to complete the M-Pesa payment.',
                raw: $payload
            );
        }

        Logger::error('M-Pesa STK push declined', ['http_code' => $httpCode, 'order_number' => $order['order_number']]);

        return new PaymentResult(
            success: false,
            status: 'failed',
            message: (string) ($payload['errorMessage'] ?? 'Could not initiate the M-Pesa payment.'),
            raw: $payload
        );
    }

    private function fetchAccessToken(): ?string
    {
        $consumerKey = (string) config('payment.mpesa.consumer_key');
        $consumerSecret = (string) config('payment.mpesa.consumer_secret');

        $ch = curl_init($this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $payload = json_decode($response, true) ?? [];

        return $payload['access_token'] ?? null;
    }

    private function baseUrl(): string
    {
        return config('payment.mpesa.env') === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
