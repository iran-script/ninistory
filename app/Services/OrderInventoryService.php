<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\{Order, StockReservation, User};
use Illuminate\Support\Facades\DB;

class OrderInventoryService
{
    public function __construct(private InventoryReservationService $reservations, private InventoryService $inventory) {}

    public function releaseReservations(Order $order): void { $this->reservations->releaseOrder($order); }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->status, [Order::CANCELLED, Order::PAYMENT_FAILED, Order::PAYMENT_EXPIRED], true)) return $locked;
            if ($locked->status === Order::FULFILLED) throw new InsufficientStockException('سفارش تکمیل‌شده قابل لغو نیست.');
            $this->reservations->releaseOrder($locked);
            $locked->update(['status' => Order::CANCELLED, 'fulfillment_status' => Order::FULFILLMENT_CANCELLED, 'cancellation_status' => Order::CANCELLATION_CANCELLED, 'cancelled_at' => now()]);
            return $locked->refresh();
        });
    }

    public function confirmPayment(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->status, [Order::PAID, Order::FULFILLED], true)) return $locked;
            if ($locked->status !== Order::PENDING_PAYMENT) throw new InsufficientStockException('وضعیت سفارش برای تأیید پرداخت معتبر نیست.');
            $this->reservations->releaseOrder($locked, StockReservation::COMMITTED);
            foreach ($locked->items as $item) $this->inventory->deductPhysical($item->variant, $item->quantity, 'payment_confirmed', null, $locked->id);
            $locked->update(['status' => Order::PAID, 'payment_status' => Order::PAYMENT_PAID, 'fulfillment_status' => Order::FULFILLMENT_READY]);
            return $locked->refresh();
        });
    }

    public function fulfill(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === Order::FULFILLED) return $locked;
            if ($locked->status !== Order::PAID) throw new InsufficientStockException('فقط سفارش پرداخت‌شده قابل تکمیل است.');
            $locked->update(['status' => Order::FULFILLED, 'fulfillment_status' => Order::FULFILLMENT_FULFILLED]);
            return $locked->refresh();
        });
    }

    public function restockReturn(Order $order, string $reason, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === Order::RETURNED) return $locked;
            if (! in_array($locked->status, [Order::PAID, Order::FULFILLED], true)) throw new InsufficientStockException('این سفارش قابل ثبت به‌عنوان مرجوعی نیست.');
            foreach ($locked->items as $item) $this->inventory->receive($item->variant, $item->quantity, $reason, $actor);
            $locked->update(['status' => Order::RETURNED, 'return_status' => Order::RETURN_CLOSED]);
            return $locked->refresh();
        });
    }
}
