<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string { return 'mock'; }

    private function guard(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('payment.mock_enabled')) throw new RuntimeException('Mock payment gateway is disabled outside local/testing or by configuration.');
    }

    public function initiatePayment(Payment $payment): PaymentGatewayResult
    {
        $this->guard();
        $reference = 'MOCK-'.strtoupper(Str::random(18));
        return PaymentGatewayResult::pending($reference, null, ['test_state' => 'pending', 'initiated_at' => now()->toIso8601String()]);
    }

    public function verifyPayment(Payment $payment): PaymentGatewayResult
    {
        $this->guard();
        $state = data_get($payment->provider_metadata, 'test_state', 'pending');
        return match ($state) {
            'success' => PaymentGatewayResult::success($payment->provider_reference ?: 'MOCK-UNKNOWN', ['test_state' => $state], (int) $payment->amount_toman),
            'failed' => PaymentGatewayResult::failed('mock_failed', 'پرداخت آزمایشی ناموفق بود.', ['test_state' => $state]),
            'cancelled' => PaymentGatewayResult::cancelled(['test_state' => $state]),
            'expired' => PaymentGatewayResult::failed('mock_expired', 'مهلت پرداخت آزمایشی به پایان رسیده است.', ['test_state' => $state]),
            default => PaymentGatewayResult::pending($payment->provider_reference ?: 'MOCK-PENDING', null, ['test_state' => 'pending']),
        };
    }

    public function queryPaymentStatus(Payment $payment): PaymentGatewayResult { return $this->verifyPayment($payment); }

    public function refundPayment(Payment $payment, int $amountToman, string $idempotencyKey): PaymentGatewayResult
    {
        $this->guard();
        return PaymentGatewayResult::success('MOCK-REFUND-'.strtoupper(Str::random(16)), ['simulated' => true, 'amount_toman' => $amountToman, 'idempotency_key' => $idempotencyKey]);
    }

    public function simulate(Payment $payment, string $state): Payment
    {
        $this->guard();
        if (! in_array($state, ['success', 'failed', 'cancelled', 'pending', 'expired'], true)) throw new RuntimeException('Unsupported mock payment state.');
        $metadata = $payment->provider_metadata ?: [];
        $metadata['test_state'] = $state;
        $payment->forceFill(['provider_metadata' => $metadata])->save();
        return $payment->refresh();
    }
}
