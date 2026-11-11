<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use RuntimeException;

class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= config('payment.gateway', 'mock');
        return match ($name) {
            'mock' => app(MockPaymentGateway::class),
            default => throw new RuntimeException('Payment gateway is not configured: '.$name),
        };
    }
}
