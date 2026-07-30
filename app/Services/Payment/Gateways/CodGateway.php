<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentResult;

/**
 * Cash on Delivery - the only gateway that needs no external service
 * and therefore always succeeds. Money is collected in person at
 * delivery, so the payment is recorded as 'pending' rather than
 * 'completed'; an admin marks it paid once the courier confirms
 * collection (Module 10 - admin order management).
 */
final class CodGateway implements PaymentGatewayInterface
{
    public function slug(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return 'Cash on Delivery';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function charge(array $order, array $input): PaymentResult
    {
        return new PaymentResult(
            success: true,
            status: 'pending',
            transactionId: null,
            message: 'Cash on delivery - payment will be collected upon delivery.'
        );
    }
}
