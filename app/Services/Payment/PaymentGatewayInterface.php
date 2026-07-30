<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * The one contract every payment gateway implements. Adding a new
 * gateway means writing one class against this interface and
 * registering it in PaymentGatewayManager - nothing else in the
 * checkout flow needs to change.
 */
interface PaymentGatewayInterface
{
    public function slug(): string;

    public function label(): string;

    /**
     * Whether this gateway has the credentials it needs to actually
     * attempt a charge (vs. just being architecturally present).
     */
    public function isConfigured(): bool;

    /**
     * @param array{order_id:int,order_number:string,total:float} $order
     * @param array<string,mixed> $input Gateway-specific input (card token, phone number, ...).
     */
    public function charge(array $order, array $input): PaymentResult;
}
