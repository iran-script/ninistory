<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Payment, Refund};
use App\Services\{PaymentVerificationService, RefundService};
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::query()->with('order')->when($request->filled('q'), fn ($q) => $q->whereHas('order', fn ($o) => $o->where('number', 'like', '%'.$request->string('q').'%'))->orWhere('provider_reference', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment) { return view('admin.payments.show', ['payment' => $payment->load(['order', 'refunds', 'order.events'])]); }

    public function verify(Payment $payment, PaymentVerificationService $verification) { $verification->verify($payment); return back()->with('status', 'درخواست بررسی پرداخت انجام شد؛ وضعیت فقط بر اساس پاسخ معتبر درگاه تغییر می‌کند.'); }

    public function reconciliationNote(Request $request, Payment $payment)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $payment->update(['reconciliation_note' => $data['note']]);
        return back()->with('status', 'یادداشت تطبیق ثبت شد.');
    }

    public function simulateRefund(Payment $payment, Refund $refund, RefundService $refunds)
    {
        abort_unless($refund->payment_id === $payment->id, 404);
        $refunds->completeMock($refund);
        return back()->with('status', 'بازپرداخت آزمایشی ثبت شد؛ هیچ وجه واقعی منتقل نشده است.');
    }
}
