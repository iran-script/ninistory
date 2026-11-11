<?php

namespace App\Console\Commands;

use App\Models\{Order, Payment};
use App\Services\PaymentVerificationService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--dry-run : Report without changing local state}';
    protected $description = 'Find payment/order mismatches and retry authoritative verification where supported';

    public function handle(PaymentVerificationService $verification): int
    {
        $issues = 0;
        Payment::query()->with('order')->where(function ($q) { $q->where('status', Payment::REQUIRES_RECONCILIATION)->orWhere('status', Payment::SUCCEEDED); })->orderBy('id')->each(function (Payment $payment) use (&$issues, $verification): void {
            $amountMismatch = (int) $payment->amount_toman !== (int) $payment->order->total_toman;
            $orderMismatch = $payment->status === Payment::SUCCEEDED && $payment->order->payment_status !== Order::PAYMENT_PAID;
            if (! $amountMismatch && ! $orderMismatch && $payment->status !== Payment::REQUIRES_RECONCILIATION) return;
            $issues++;
            $this->line("Payment {$payment->payment_uuid}: reconciliation required");
            if (! $this->option('dry-run') && ! $amountMismatch) $verification->verify($payment);
        });
        $this->info("Found {$issues} reconciliation issue(s).");
        return self::SUCCESS;
    }
}
