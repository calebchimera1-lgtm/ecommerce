<?php

declare(strict_types=1);

namespace App\Services\Order;

use RuntimeException;

/**
 * A checkout-stage failure with a message safe to show the customer
 * directly (stock ran out, payment was declined, ...).
 */
final class OrderPlacementException extends RuntimeException
{
}
