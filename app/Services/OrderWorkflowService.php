<?php

namespace App\Services;

use App\Models\{Order, OrderEvent, Shipment, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public function __construct(private OrderInventoryService $inventory, private RefundService $refunds) {}

    private function event(Order $order, string $type, array $before, array $after, ?User $actor, ?string $reason = null, ?string $key = null): void
    {
        if ($key && OrderEvent::query()->where('idempotency_key', $key)->exists()) return;
        $order->events()->create(['event_type' => $type, 'actor_id' => $actor?->id, 'previous_state' => $before, 'new_state' => $after, 'reason' => $reason, 'idempotency_key' => $key]);
    }

    public function startProcessing(Order $order, User $actor): Order { return $this->transition($order, $actor, 'fulfillment_status', Order::FULFILLMENT_PROCESSING, [Order::FULFILLMENT_READY], 'processing'); }
    public function pack(Order $order, User $actor): Order { return $this->transition($order, $actor, 'fulfillment_status', Order::FULFILLMENT_PACKED, [Order::FULFILLMENT_PROCESSING], 'packed'); }

    public function ship(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $key = 'ship:'.$locked->id;
            if ($locked->shipments()->where('idempotency_key', $key)->exists()) return $locked->refresh();
            if ($locked->payment_status !== Order::PAYMENT_PAID || $locked->fulfillment_status !== Order::FULFILLMENT_PACKED) throw ValidationException::withMessages(['order' => 'فقط سفارش پرداخت‌شده و بسته‌بندی‌شده قابل ارسال است.']);
            $shipment = $locked->shipments()->create(['carrier' => $data['carrier'] ?? null, 'shipping_method' => $data['shipping_method'] ?? null, 'tracking_code' => $data['tracking_code'] ?? null, 'idempotency_key' => $key, 'status' => Order::SHIPMENT_SHIPPED, 'shipped_at' => now(), 'internal_notes' => $data['internal_notes'] ?? null]);
            $before = ['shipment_status' => $locked->shipment_status];
            $locked->update(['shipment_status' => Order::SHIPMENT_SHIPPED]);
            $this->event($locked, 'shipped', $before, ['shipment_status' => Order::SHIPMENT_SHIPPED, 'shipment_id' => $shipment->id], $actor, null, $key);
            return $locked->refresh();
        });
    }

    public function updateShipmentStatus(Order $order, string $status, User $actor, ?string $note = null): Order
    {
        $allowed = [Order::SHIPMENT_SHIPPED, Order::SHIPMENT_IN_TRANSIT, Order::SHIPMENT_FAILED, Order::SHIPMENT_RETURNED];
        if (! in_array($status, $allowed, true)) throw ValidationException::withMessages(['status' => 'وضعیت ارسال معتبر نیست.']);
        return DB::transaction(function () use ($order, $status, $actor, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->shipment_status === $status) return $locked;
            if (! in_array($locked->shipment_status, [Order::SHIPMENT_SHIPPED, Order::SHIPMENT_IN_TRANSIT], true)) throw ValidationException::withMessages(['order' => 'این تغییر وضعیت ارسال مجاز نیست.']);
            $shipment = $locked->shipments()->latest('id')->first();
            if (! $shipment) throw ValidationException::withMessages(['order' => 'برای سفارش ارسال ثبت نشده است.']);
            $before = ['shipment_status' => $locked->shipment_status];
            $shipment->update(['status' => $status, 'internal_notes' => $note ?: $shipment->internal_notes]);
            $locked->update(['shipment_status' => $status]);
            $this->event($locked, 'shipment_status_changed', $before, ['shipment_status' => $status], $actor, $note, 'shipment-status:'.$locked->id.':'.$status);
            return $locked->refresh();
        });
    }

    public function deliver(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->shipment_status === Order::SHIPMENT_DELIVERED && $locked->fulfillment_status === Order::FULFILLMENT_FULFILLED) return $locked;
            if ($locked->payment_status !== Order::PAYMENT_PAID || $locked->fulfillment_status !== Order::FULFILLMENT_PACKED || ! in_array($locked->shipment_status, [Order::SHIPMENT_SHIPPED, Order::SHIPMENT_IN_TRANSIT], true)) throw ValidationException::withMessages(['order' => 'تحویل فقط پس از پرداخت، بسته‌بندی و ارسال مجاز است.']);
            $before = ['fulfillment_status' => $locked->fulfillment_status, 'shipment_status' => $locked->shipment_status];
            $locked->update(['shipment_status' => Order::SHIPMENT_DELIVERED, 'fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'status' => Order::FULFILLED]);
            $locked->shipments()->latest('id')->first()?->update(['status' => Order::SHIPMENT_DELIVERED, 'delivered_at' => now()]);
            $this->event($locked, 'delivered', $before, ['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'shipment_status' => Order::SHIPMENT_DELIVERED], $actor, null, 'deliver:'.$locked->id);
            return $locked->refresh();
        });
    }

    public function cancel(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->cancellation_status === Order::CANCELLATION_CANCELLED || $locked->fulfillment_status === Order::FULFILLMENT_CANCELLED) return $locked;
            if ($locked->fulfillment_status === Order::FULFILLMENT_FULFILLED || $locked->shipment_status === Order::SHIPMENT_DELIVERED) throw ValidationException::withMessages(['order' => 'سفارش تحویل‌شده قابل لغو نیست.']);
            $paid = $locked->payment_status === Order::PAYMENT_PAID;
            if (! $paid) $this->inventory->releaseReservations($locked);
            $before = ['payment_status' => $locked->payment_status, 'fulfillment_status' => $locked->fulfillment_status, 'cancellation_status' => $locked->cancellation_status, 'refund_status' => $locked->refund_status];
            $locked->update(['status' => Order::CANCELLED, 'fulfillment_status' => Order::FULFILLMENT_CANCELLED, 'cancellation_status' => Order::CANCELLATION_CANCELLED, 'refund_status' => $paid ? Order::REFUND_PENDING : Order::REFUND_NONE, 'cancel_reason' => $reason, 'cancelled_at' => now()]);
            if ($paid && $locked->payments()->where('status', \App\Models\Payment::SUCCEEDED)->exists()) $this->refunds->request($locked, $locked->total_toman, $actor, $reason);
            $this->event($locked, 'cancelled', $before, ['fulfillment_status' => Order::FULFILLMENT_CANCELLED, 'cancellation_status' => Order::CANCELLATION_CANCELLED, 'refund_status' => $locked->refund_status], $actor, $reason, 'cancel:'.$locked->id);
            return $locked->refresh();
        });
    }

    public function note(Order $order, User $actor, string $body): void
    {
        DB::transaction(function () use ($order, $actor, $body): void { $order->notes()->create(['user_id' => $actor->id, 'body' => $body]); $this->event($order, 'note_added', [], [], $actor, $body); });
    }

    private function transition(Order $order, User $actor, string $field, string $to, array $from, string $type): Order
    {
        return DB::transaction(function () use ($order, $actor, $field, $to, $from, $type): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->{$field} === $to) return $locked;
            if (! in_array($locked->{$field}, $from, true) || $locked->payment_status !== Order::PAYMENT_PAID) throw ValidationException::withMessages(['order' => 'ترتیب تغییر وضعیت سفارش معتبر نیست.']);
            $old = $locked->{$field};
            $locked->update([$field => $to]);
            $this->event($locked, $type, [$field => $old], [$field => $to], $actor, null, $type.':'.$locked->id);
            return $locked->refresh();
        });
    }
}
