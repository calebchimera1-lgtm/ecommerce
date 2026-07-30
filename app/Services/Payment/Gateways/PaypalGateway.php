<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Core\Logger;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentResult;

/**
 * PayPal Orders API v2. The customer approves the payment client-side
 * via the PayPal JS SDK button, which returns a PayPal order ID; this
 * gateway then captures that order server-side. Requires
 * PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET.
 */
final class PaypalGateway implements PaymentGatewayInterface
{
    public function slug(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isConfigured(): bool
    {
        return (string) config('payment.paypal.client_id') !== ''
            && (string) config('payment.paypal.client_secret') !== '';
    }

    /**
     * $input is expected to carry 'paypal_order_id' - the order ID
     * returned by the PayPal JS SDK after the customer approves
     * payment in the PayPal popup.
     */
    public function charge(array $order, array $input): PaymentResult
    {
        if (!$this->isConfigured()) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                message: 'PayPal is not configured. Set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET to enable it.'
            );
        }

        $paypalOrderId = (string) ($input['paypal_order_id'] ?? '');

        if ($paypalOrderId === '') {
            return new PaymentResult(success: false, status: 'failed', message: 'No PayPal order was provided.');
        }

        $accessToken = $this->fetchAccessToken();

        if ($accessToken === null) {
            return new PaymentResult(success: false, status: 'failed', message: 'Could not authenticate with PayPal. Please try again.');
        }

        $baseUrl = $this->baseUrl();
        $response = $this->request(
            'POST',
            $baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture',
            $accessToken,
            null
        );

        if ($response === null) {
            return new PaymentResult(success: false, status: 'failed', message: 'Could not reach PayPal. Please try again.');
        }

        [$httpCode, $payload] = $response;

        if ($httpCode >= 200 && $httpCode < 300 && ($payload['status'] ?? '') === 'COMPLETED') {
            $captureId = $payload['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypalOrderId;

            return new PaymentResult(
                success: true,
                status: 'completed',
                transactionId: (string) $captureId,
                message: 'PayPal payment successful.',
                raw: $payload
            );
        }

        Logger::error('PayPal capture failed', ['http_code' => $httpCode, 'order_number' => $order['order_number']]);

        return new PaymentResult(
            success: false,
            status: 'failed',
            message: (string) ($payload['message'] ?? 'The PayPal payment could not be completed.'),
            raw: $payload
        );
    }

    private function fetchAccessToken(): ?string
    {
        $clientId = (string) config('payment.paypal.client_id');
        $clientSecret = (string) config('payment.paypal.client_secret');

        $ch = curl_init($this->baseUrl() . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
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

    /**
     * @return array{0:int,1:array}|null [http status, decoded JSON body]
     */
    private function request(string $method, string $url, string $accessToken, ?array $body): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : '',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        return [$httpCode, json_decode($response, true) ?? []];
    }

    private function baseUrl(): string
    {
        return config('payment.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
