<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function initiate(Order $order, ?int $userId = null, ?string $guestToken = null, ?string $idempotencyKey = null, ?string $sessionOrderNumber = null): Payment
    {
        $this->assertOwnership($order, $userId, $guestToken, $sessionOrderNumber);
        if ($order->payment_status !== Order::PAYMENT_PENDING || $order->fulfillment_status !== Order::FULFILLMENT_AWAITING) {
            throw ValidationException::withMessages(['order' => 'این سفارش در وضعیت قابل پرداخت نیست.']);
        }
        if (! $order->reservations()->where('status', 'active')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['order' => 'مهلت رزرو موجودی این سفارش به پایان رسیده است.']);
        }

        $gatewayName = config('payment.gateway', 'mock');
        if ($gatewayName === 'mock' && (! app()->environment(['local', 'testing']) || ! config('payment.mock_enabled'))) {
            abort(404);
        }
        if ($idempotencyKey && ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first())) {
            if ((int) $existing->order_id !== (int) $order->id) {
                throw ValidationException::withMessages(['payment' => 'کلید تکرارنشدنی پرداخت به سفارش دیگری تعلق دارد.']);
            }

            return $existing;
        }
        $active = $order->payments()->whereIn('status', [Payment::CREATED, Payment::PENDING])->latest('id')->first();
        if ($active && $active->expires_at?->isFuture()) {
            return $active;
        }
        $key = $idempotencyKey ?: 'payment:'.$order->id.':'.Str::uuid();

        $payment = DB::transaction(function () use ($order, $gatewayName, $key): Payment {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_status !== Order::PAYMENT_PENDING || ! $locked->reservations()->where('status', 'active')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['payment' => 'سفارش دیگر قابل پرداخت نیست.']);
            }
            $active = $locked->payments()->whereIn('status', [Payment::CREATED, Payment::PENDING])->where('expires_at', '>', now())->latest('id')->first();
            if ($active) {
                return $active;
            }
            $attempt = (int) $locked->payments()->max('attempt_number') + 1;

            return $locked->payments()->create(['payment_uuid' => (string) Str::uuid(), 'provider' => $gatewayName, 'currency' => 'IRR', 'amount_toman' => $locked->total_toman, 'amount_rials' => $locked->total_toman * 10, 'idempotency_key' => $key, 'status' => Payment::CREATED, 'attempt_number' => $attempt, 'initiated_at' => now(), 'expires_at' => now()->addMinutes(config('payment.attempt_ttl_minutes', 15))]);
        });

        try {
            $result = $this->gateways->driver($gatewayName)->initiatePayment($payment);
            $metadata = array_merge($payment->provider_metadata ?: [], $result->metadata);
            $payment->update(['status' => Payment::PENDING, 'provider_reference' => $result->providerReference, 'provider_authority' => $result->authority, 'provider_metadata' => $metadata]);
            $order->events()->firstOrCreate(['idempotency_key' => 'payment-initiated:'.$payment->id], ['event_type' => 'payment_initiated', 'new_state' => ['payment_id' => $payment->id, 'provider' => $payment->provider, 'amount_toman' => $payment->amount_toman]]);
        } catch (\Throwable $exception) {
            $payment->update(['status' => Payment::REQUIRES_RECONCILIATION, 'failure_code' => 'initiation_unknown', 'failure_reason' => 'Gateway initiation outcome is unknown.']);
            throw ValidationException::withMessages(['payment' => 'وضعیت ارتباط با درگاه مشخص نیست؛ لطفاً دوباره تلاش نکنید و با پشتیبانی تماس بگیرید.']);
        }

        return $payment->refresh();
    }

    public function assertOwnership(Order $order, ?int $userId, ?string $guestToken, ?string $sessionOrderNumber = null): void
    {
        $owned = $userId !== null && $order->user_id === $userId;
        $guestOwned = $guestToken !== null && hash_equals((string) $order->guest_access_token, $guestToken);
        $sessionOwned = $sessionOrderNumber !== null && hash_equals((string) $order->number, $sessionOrderNumber);
        if (! $owned && ! $guestOwned && ! $sessionOwned) {
            abort(404);
        }
    }
}
