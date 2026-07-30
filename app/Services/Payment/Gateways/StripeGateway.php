<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Core\Logger;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentResult;

/**
 * Card payments (Visa/Mastercard/etc.) via Stripe. Real integration
 * against Stripe's REST API - implemented with a plain cURL call
 * rather than the stripe-php SDK, since this codebase otherwise has no
 * dependency on it and the call shape (one POST, bearer auth,
 * form-encoded body) doesn't warrant pulling in a full SDK.
 *
 * Requires STRIPE_SECRET_KEY to be configured; without it, isConfigured()
 * is false and the checkout UI marks this option unavailable rather
 * than letting a customer select a gateway that can't actually charge
 * anything.
 */
final class StripeGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.stripe.com/v1/payment_intents';

    public function slug(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Credit / Debit Card (Stripe)';
    }

    public function isConfigured(): bool
    {
        return (string) config('payment.stripe.secret_key') !== '';
    }

    /**
     * $input is expected to carry 'payment_method' - the PaymentMethod
     * ID produced client-side by Stripe.js/Stripe Elements after the
     * customer enters their card details. Card numbers themselves
     * never touch this server, per PCI best practice.
     */
    public function charge(array $order, array $input): PaymentResult
    {
        if (!$this->isConfigured()) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                message: 'Card payments are not configured. Set STRIPE_SECRET_KEY to enable Stripe.'
            );
        }

        $paymentMethod = (string) ($input['payment_method'] ?? '');

        if ($paymentMethod === '') {
            return new PaymentResult(success: false, status: 'failed', message: 'No card payment method was provided.');
        }

        $secretKey = (string) config('payment.stripe.secret_key');
        $amountInCents = (int) round($order['total'] * 100);

        $body = http_build_query([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'payment_method' => $paymentMethod,
            'confirm' => 'true',
            'description' => 'Kymera Collection order ' . $order['order_number'],
            'metadata[order_id]' => (string) $order['order_id'],
            'metadata[order_number]' => $order['order_number'],
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('Stripe charge failed (transport error)', ['error' => $curlError, 'order_number' => $order['order_number']]);

            return new PaymentResult(success: false, status: 'failed', message: 'Could not reach the card payment processor. Please try again.');
        }

        $payload = json_decode($response, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300 && ($payload['status'] ?? '') === 'succeeded') {
            return new PaymentResult(
                success: true,
                status: 'completed',
                transactionId: (string) ($payload['id'] ?? ''),
                message: 'Card payment successful.',
                raw: $payload
            );
        }

        Logger::error('Stripe charge declined', ['http_code' => $httpCode, 'order_number' => $order['order_number']]);

        return new PaymentResult(
            success: false,
            status: 'failed',
            message: (string) ($payload['error']['message'] ?? 'The card payment could not be completed.'),
            raw: $payload
        );
    }
}
