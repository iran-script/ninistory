<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\PaymentGatewayResult;

interface PaymentGatewayInterface
{
    public function name(): string;
    public function initiatePayment(Payment $payment): PaymentGatewayResult;
    public function verifyPayment(Payment $payment): PaymentGatewayResult;
    public function queryPaymentStatus(Payment $payment): PaymentGatewayResult;
    public function refundPayment(Payment $payment, int $amountToman, string $idempotencyKey): PaymentGatewayResult;
}
