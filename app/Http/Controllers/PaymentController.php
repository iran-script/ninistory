<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\{MockPaymentGateway, PaymentService, PaymentVerificationService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function initiate(Request $request, \App\Models\Order $order, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['access_token' => ['nullable', 'string', 'size:128'], 'idempotency_key' => ['nullable', 'string', 'max:160']]);
        $payment = $payments->initiate($order, $request->user()?->id, $data['access_token'] ?? null, $data['idempotency_key'] ?? null, $request->session()->get('completed_order_number'));
        if ($payment->provider === 'mock') return redirect()->to(URL::temporarySignedRoute('payments.mock.show', now()->addMinutes(15), ['payment' => $payment]));
        abort(501, 'Configured payment gateway does not yet provide a customer redirect.');
    }

    public function mockShow(Payment $payment): View { $this->assertMock($payment); return view('store.payments.mock', compact('payment')); }

    public function mockAction(Request $request, Payment $payment, MockPaymentGateway $mock, PaymentVerificationService $verification): RedirectResponse
    {
        $this->assertMock($payment);
        $data = $request->validate(['action' => ['required', 'in:success,failed,cancelled,pending,expired']]);
        $mock->simulate($payment, $data['action']);
        $payment = $verification->verify($payment->fresh());
        return redirect()->to(URL::temporarySignedRoute('payments.result', now()->addMinutes(15), ['payment' => $payment]));
    }

    public function result(Payment $payment): View { $payment->load('order'); abort_unless($payment->provider === 'mock', 404); return view('store.payments.result', compact('payment')); }

    private function assertMock(Payment $payment): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('payment.mock_enabled') && $payment->provider === 'mock', 404);
        abort_unless($payment->expires_at?->isFuture() && in_array($payment->status, [Payment::CREATED, Payment::PENDING], true), 410);
    }
}
