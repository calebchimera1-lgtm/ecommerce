<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\Gateways\CodGateway;
use App\Services\Payment\Gateways\MpesaGateway;
use App\Services\Payment\Gateways\PaypalGateway;
use App\Services\Payment\Gateways\StripeGateway;
use InvalidArgumentException;

/**
 * Registry for every payment gateway. Adding a new one means adding a
 * single entry to self::gateways() - the checkout controller, view,
 * and order placement service never need to know the concrete list.
 */
final class PaymentGatewayManager
{
    /**
     * @return PaymentGatewayInterface[]
     */
    private static function gateways(): array
    {
        return [
            new CodGateway(),
            new StripeGateway(),
            new PaypalGateway(),
            new MpesaGateway(),
        ];
    }

    public static function resolve(string $slug): PaymentGatewayInterface
    {
        foreach (self::gateways() as $gateway) {
            if ($gateway->slug() === $slug) {
                return $gateway;
            }
        }

        throw new InvalidArgumentException("Unknown payment gateway: {$slug}");
    }

    public static function isConfigured(string $slug): bool
    {
        foreach (self::gateways() as $gateway) {
            if ($gateway->slug() === $slug) {
                return $gateway->isConfigured();
            }
        }

        return false;
    }

    /**
     * @return array<int,array{slug:string,label:string,configured:bool}>
     */
    public static function available(): array
    {
        return array_map(
            static fn (PaymentGatewayInterface $gateway): array => [
                'slug' => $gateway->slug(),
                'label' => $gateway->label(),
                'configured' => $gateway->isConfigured(),
            ],
            self::gateways()
        );
    }
}
