<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, ReturnRequest};
use App\Services\{InventoryService, OrderWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()->withCount('items')->with('assignedOperator')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($x) => $x->where('number', 'like', '%'.$request->string('q').'%')->orWhere('recipient_name', 'like', '%'.$request->string('q').'%')->orWhere('phone', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('fulfillment_status'), fn ($q) => $q->where('fulfillment_status', $request->string('fulfillment_status')))
            ->when($request->filled('shipment_status'), fn ($q) => $q->where('shipment_status', $request->string('shipment_status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->boolean('awaiting_action'), fn ($q) => $q->where(function ($x) { $x->where('payment_status', Order::PAYMENT_PENDING)->orWhere('fulfillment_status', Order::FULFILLMENT_READY)->orWhereIn('shipment_status', [Order::SHIPMENT_FAILED, Order::SHIPMENT_RETURNED]); }))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', ['order' => $order->load(['items', 'events.actor', 'shipments', 'notes', 'reservations', 'payments', 'returns.items.orderItem'])]);
    }

    public function process(Order $order, OrderWorkflowService $workflow) { $workflow->startProcessing($order, request()->user()); return back()->with('status', 'سفارش وارد مرحله پردازش شد.'); }
    public function pack(Order $order, OrderWorkflowService $workflow) { $workflow->pack($order, request()->user()); return back()->with('status', 'بسته‌بندی سفارش ثبت شد.'); }

    public function ship(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        $data = $request->validate(['carrier' => ['nullable', 'string', 'max:80'], 'shipping_method' => ['nullable', 'string', 'max:80'], 'tracking_code' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'], 'internal_notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->ship($order, $data, $request->user());
        return back()->with('status', 'اطلاعات ارسال ثبت شد.');
    }

    public function shipmentStatus(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        $data = $request->validate(['status' => ['required', 'in:in_transit,failed_delivery,returned_to_sender'], 'note' => ['nullable', 'string', 'max:2000']]);
        $workflow->updateShipmentStatus($order, $data['status'], $request->user(), $data['note'] ?? null);
        return back()->with('status', 'وضعیت ارسال به‌روزرسانی شد.');
    }

    public function deliver(Order $order, OrderWorkflowService $workflow) { $workflow->deliver($order, request()->user()); return back()->with('status', 'تحویل سفارش ثبت شد.'); }
    public function cancel(Request $request, Order $order, OrderWorkflowService $workflow) { $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]); $workflow->cancel($order, $request->user(), $data['reason']); return back()->with('status', 'لغو سفارش ثبت شد.'); }
    public function note(Request $request, Order $order, OrderWorkflowService $workflow) { $data = $request->validate(['body' => ['required', 'string', 'max:4000']]); $workflow->note($order, $request->user(), $data['body']); return back()->with('status', 'یادداشت ثبت شد.'); }

    public function requestReturn(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1'], 'items.*' => ['integer', 'min:1']]);
        abort_unless(in_array($order->payment_status, [Order::PAYMENT_PAID, Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED], true), 422, 'فقط سفارش پرداخت‌شده قابل مرجوعی است.');
        $items = $order->items()->whereIn('id', array_keys($data['items']))->get()->keyBy('id');
        DB::transaction(function () use ($order, $data, $items, $request): void {
            $return = $order->returns()->create(['user_id' => $request->user()->id, 'status' => Order::RETURN_REQUESTED, 'reason' => $data['reason']]);
            foreach ($data['items'] as $id => $quantity) {
                $item = $items->get($id);
                if (! $item) throw ValidationException::withMessages(['items' => 'قلم سفارش معتبر نیست.']);
                $already = $order->returns()->whereNotIn('status', [Order::RETURN_REJECTED])->whereHas('items', fn ($q) => $q->where('order_item_id', $id))->with('items')->get()->flatMap->items->where('order_item_id', $id)->sum('quantity');
                if ($already + $quantity > $item->quantity) throw ValidationException::withMessages(['items' => 'تعداد مرجوعی از تعداد خرید بیشتر است.']);
                $return->items()->create(['order_item_id' => $id, 'quantity' => $quantity]);
            }
            $order->update(['return_status' => Order::RETURN_REQUESTED]);
        });
        $order->events()->create(['event_type' => 'return_requested', 'actor_id' => $request->user()->id, 'new_state' => ['return_id' => $order->returns()->latest('id')->value('id'), 'return_status' => Order::RETURN_REQUESTED], 'reason' => $data['reason'], 'idempotency_key' => 'return-request:'.$order->id.':'.sha1($data['reason'].json_encode($data['items']))]);
        return back()->with('status', 'درخواست مرجوعی ثبت شد.');
    }

    public function approveReturn(Request $request, Order $order, ReturnRequest $return) { $this->assertReturn($order, $return, [Order::RETURN_REQUESTED, Order::RETURN_REVIEW]); $return->update(['status' => Order::RETURN_APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now()]); $order->update(['return_status' => Order::RETURN_APPROVED]); $this->returnEvent($order, $request, 'return_approved', $return); return back()->with('status', 'مرجوعی تأیید شد.'); }
    public function rejectReturn(Request $request, Order $order, ReturnRequest $return) { $this->assertReturn($order, $return, [Order::RETURN_REQUESTED, Order::RETURN_REVIEW]); $data = $request->validate(['admin_note' => ['required', 'string', 'max:2000']]); $return->update(['status' => Order::RETURN_REJECTED, 'admin_note' => $data['admin_note']]); $order->update(['return_status' => Order::RETURN_REJECTED]); $this->returnEvent($order, $request, 'return_rejected', $return, $data['admin_note']); return back()->with('status', 'مرجوعی رد شد.'); }
    public function receiveReturn(Request $request, Order $order, ReturnRequest $return) { $this->assertReturn($order, $return, [Order::RETURN_APPROVED]); $return->update(['status' => Order::RETURN_RECEIVED, 'received_at' => now()]); $order->update(['return_status' => Order::RETURN_RECEIVED]); $this->returnEvent($order, $request, 'return_received', $return); return back()->with('status', 'دریافت مرجوعی ثبت شد.'); }

    public function inspectReturn(Request $request, Order $order, ReturnRequest $return)
    {
        $this->assertReturn($order, $return, [Order::RETURN_RECEIVED]);
        $data = $request->validate(['disposition' => ['required', 'in:restock,writeoff'], 'inspection_note' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($order, $return, $request, $data): void {
            $return->items()->update(['disposition' => $data['disposition'], 'inspection_note' => $data['inspection_note'] ?? null]);
            $return->update(['status' => Order::RETURN_INSPECTED, 'inspected_by' => $request->user()->id, 'inspected_at' => now()]);
            $order->update(['return_status' => Order::RETURN_INSPECTED]);
        });
        $this->returnEvent($order, $request, 'return_inspected', $return, $data['disposition']);
        return back()->with('status', 'بازرسی مرجوعی ثبت شد.');
    }

    public function restockReturn(Request $request, Order $order, ReturnRequest $return, InventoryService $inventory)
    {
        $this->assertReturn($order, $return, [Order::RETURN_INSPECTED]);
        DB::transaction(function () use ($request, $order, $return, $inventory): void {
            foreach ($return->items()->where('disposition', 'restock')->with('orderItem.variant')->lockForUpdate()->get() as $item) {
                if ($item->orderItem?->variant) $inventory->receive($item->orderItem->variant, $item->quantity, 'authorized_return', $request->user());
                $item->update(['disposition' => 'restocked']);
            }
            $return->update(['status' => Order::RETURN_CLOSED, 'closed_at' => now()]);
            $order->update(['return_status' => Order::RETURN_CLOSED, 'status' => Order::RETURNED]);
        });
        $this->returnEvent($order, $request, 'return_restocked', $return);
        return back()->with('status', 'مرجوعی به موجودی بازگشت.');
    }

    private function assertReturn(Order $order, ReturnRequest $return, array $statuses): void { abort_unless($return->order_id === $order->id && in_array($return->status, $statuses, true), 422, 'وضعیت مرجوعی معتبر نیست.'); }
    private function returnEvent(Order $order, Request $request, string $type, ReturnRequest $return, ?string $reason = null): void { if (! $order->events()->where('idempotency_key', $type.':'.$return->id)->exists()) $order->events()->create(['event_type' => $type, 'actor_id' => $request->user()->id, 'new_state' => ['return_id' => $return->id, 'status' => $return->status], 'reason' => $reason, 'idempotency_key' => $type.':'.$return->id]); }
}
