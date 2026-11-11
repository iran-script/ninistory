<?php

namespace App\Services;

use App\Models\{Order, Payment, Refund, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function request(Order $order, int $amountToman, ?User $actor, string $reason): Refund
    {
        if ($amountToman < 1) throw ValidationException::withMessages(['refund' => 'مبلغ بازپرداخت معتبر نیست.']);
        return DB::transaction(function () use ($order, $amountToman, $actor, $reason): Refund {
            $payment = $order->payments()->whereIn('status', [Payment::SUCCEEDED, Payment::REFUNDED, Payment::PARTIALLY_REFUNDED])->latest('id')->lockForUpdate()->first();
            if (! $payment) throw ValidationException::withMessages(['refund' => 'پرداخت موفقی برای بازپرداخت پیدا نشد.']);
            $captured = (int) $payment->amount_toman;
            $refunded = (int) $payment->refunds()->whereIn('status', [Refund::REQUESTED, Refund::PENDING, Refund::SUCCEEDED])->sum('amount_toman');
            if ($refunded + $amountToman > $captured) throw ValidationException::withMessages(['refund' => 'مجموع بازپرداخت از مبلغ پرداخت بیشتر است.']);
            $key = 'refund:'.$payment->id.':'.($refunded + $amountToman);
            $existing = Refund::query()->where('idempotency_key', $key)->first();
            if ($existing) return $existing;
            $refund = $order->refunds()->create(['payment_id' => $payment->id, 'requested_by' => $actor?->id, 'amount_toman' => $amountToman, 'currency' => 'IRR', 'reason' => $reason, 'status' => Refund::REQUESTED, 'idempotency_key' => $key]);
            $order->update(['refund_status' => Order::REFUND_PENDING]);
            $order->events()->firstOrCreate(['idempotency_key' => 'refund-requested:'.$refund->id], ['event_type' => 'refund_requested', 'actor_id' => $actor?->id, 'new_state' => ['refund_id' => $refund->id, 'amount_toman' => $amountToman], 'reason' => $reason]);
            return $refund;
        });
    }

    public function completeMock(Refund $refund): Refund
    {
        if (! app()->environment(['local', 'testing']) || ! config('payment.mock_enabled')) throw ValidationException::withMessages(['refund' => 'بازپرداخت آزمایشی در این محیط فعال نیست.']);
        if ($refund->status === Refund::SUCCEEDED) return $refund;
        $result = $this->gateways->driver('mock')->refundPayment($refund->payment, $refund->amount_toman, $refund->idempotency_key);
        return DB::transaction(function () use ($refund, $result): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status === Refund::SUCCEEDED) return $locked;
            $locked->update(['status' => Refund::SUCCEEDED, 'provider_reference' => $result->providerReference, 'metadata' => $result->metadata, 'completed_at' => now()]);
            $payment = $locked->payment()->lockForUpdate()->firstOrFail();
            $sum = (int) $payment->refunds()->where('status', Refund::SUCCEEDED)->sum('amount_toman');
            $payment->update(['status' => $sum >= (int) $payment->amount_toman ? Payment::REFUNDED : Payment::PARTIALLY_REFUNDED]);
            $locked->order()->update(['refund_status' => $sum >= (int) $payment->amount_toman ? Order::REFUND_COMPLETE : Order::REFUND_PENDING]);
            $locked->order->events()->firstOrCreate(['idempotency_key' => 'refund-completed:'.$locked->id], ['event_type' => 'refund_completed', 'new_state' => ['refund_id' => $locked->id, 'amount_toman' => $locked->amount_toman]]);
            return $locked->refresh();
        });
    }
}
