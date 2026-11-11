<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\{PaymentGatewayManager, PaymentVerificationService};
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    protected $signature = 'payments:expire';
    protected $description = 'Expire locally stale payment attempts without assuming external providers failed';

    public function handle(PaymentGatewayManager $gateways, PaymentVerificationService $verification): int
    {
        $count = 0;
        Payment::query()->whereIn('status', [Payment::CREATED, Payment::PENDING])->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->each(function (Payment $payment) use (&$count, $gateways, $verification): void {
            if ($payment->provider === 'mock') {
                if (! app()->environment(['local', 'testing']) || ! config('payment.mock_enabled')) { $payment->update(['status' => Payment::REQUIRES_RECONCILIATION, 'reconciliation_note' => 'Mock provider is disabled; outcome was not assumed.']); $count++; return; }
                $result = $gateways->driver('mock')->queryPaymentStatus($payment);
                if ($result->status === 'succeeded') { $verification->verify($payment); return; }
                if ($result->status === 'pending') $payment->update(['status' => Payment::EXPIRED, 'failure_code' => 'local_expired', 'failure_reason' => 'Mock attempt expired without a successful test outcome.']);
                else $verification->verify($payment);
            } else {
                $payment->update(['status' => Payment::REQUIRES_RECONCILIATION, 'reconciliation_note' => 'Local timeout; external outcome must be queried by the provider adapter.']);
            }
            $count++;
        });
        $this->info("Expired or flagged {$count} payment attempt(s).");
        return self::SUCCESS;
    }
}
