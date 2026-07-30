<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Immutable outcome of a gateway charge attempt. `status` mirrors the
 * `payments.status` enum (pending/completed/failed/refunded) so it can
 * be persisted directly.
 */
final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }
}
