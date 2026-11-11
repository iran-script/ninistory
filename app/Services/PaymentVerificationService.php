<?php

namespace App\Services;

use App\Models\{Order, OrderEvent, Payment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(private PaymentGatewayManager $gateways, private OrderInventoryService $inventory) {}

    public function verify(Payment $payment): Payment
    {
        $payment->loadMissing('order');
        if (in_array($payment->status, [Payment::SUCCEEDED, Payment::FAILED, Payment::CANCELLED], true)) return $payment;
        $result = $this->gateways->driver($payment->provider)->verifyPayment($payment);
        return DB::transaction(function () use ($payment, $result): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (in_array($locked->status, [Payment::SUCCEEDED, Payment::FAILED, Payment::CANCELLED], true)) return $locked;
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            if ($result->status === 'pending') { $locked->update(['status' => Payment::PENDING]); return $locked->refresh(); }
            if ($result->status === 'failed' || $result->status === 'cancelled') {
                $locked->update(['status' => $result->status === 'cancelled' ? Payment::CANCELLED : Payment::FAILED, 'failure_code' => $result->failureCode, 'failure_reason' => mb_substr((string) $result->failureReason, 0, 500), 'provider_metadata' => array_merge($locked->provider_metadata ?: [], $result->metadata)]);
                $this->event($order, $result->status === 'cancelled' ? 'payment_cancelled' : 'payment_failed', ['payment_id' => $locked->id, 'status' => $locked->status]);
                return $locked->refresh();
            }
            if ($result->status !== 'succeeded' || $result->providerReference === null || ($result->amountToman !== null && (int) $result->amountToman !== (int) $locked->amount_toman) || (int) $locked->amount_toman !== (int) $order->total_toman) throw ValidationException::withMessages(['payment' => 'نتیجه پرداخت معتبر نیست.']);
            $duplicate = Payment::query()->where('provider', $locked->provider)->where('provider_reference', $result->providerReference)->where('id', '<>', $locked->id)->where('status', Payment::SUCCEEDED)->exists();
            if ($duplicate) throw ValidationException::withMessages(['payment' => 'مرجع پرداخت تکراری است.']);
            $locked->update(['status' => Payment::SUCCEEDED, 'provider_reference' => $result->providerReference, 'verified_at' => now(), 'provider_metadata' => array_merge($locked->provider_metadata ?: [], $result->metadata)]);
            $hasReservation = $order->reservations()->where('status', 'active')->where('expires_at', '>', now())->exists();
            if ($order->status === Order::PENDING_PAYMENT && $hasReservation) {
                $this->inventory->confirmPayment($order);
                $this->event($order->fresh(), 'payment_verified', ['payment_id' => $locked->id]);
            } else {
                $order->update(['status' => Order::CANCELLED, 'payment_status' => Order::PAYMENT_PAID, 'fulfillment_status' => Order::FULFILLMENT_CANCELLED, 'cancellation_status' => Order::CANCELLATION_CANCELLED, 'refund_status' => Order::REFUND_PENDING, 'cancel_reason' => 'Verified payment requires manual reconciliation.', 'cancelled_at' => now()]);
                $locked->update(['status' => Payment::REQUIRES_RECONCILIATION, 'reconciliation_note' => 'Verified payment arrived after order/reservation became ineligible.']);
                $this->event($order, 'payment_reconciliation_required', ['payment_id' => $locked->id]);
            }
            return $locked->refresh();
        });
    }

    private function event(Order $order, string $type, array $state): void
    {
        $key = $type.':'.($state['payment_id'] ?? $order->id);
        if (! $order->events()->where('idempotency_key', $key)->exists()) $order->events()->create(['event_type' => $type, 'new_state' => $state, 'idempotency_key' => $key]);
    }
}
