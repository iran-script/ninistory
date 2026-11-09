<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class OrderInventoryService
{
    public function __construct(private InventoryReservationService $reservations, private InventoryService $inventory) {}
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) { $order = Order::query()->lockForUpdate()->findOrFail($order->id); if (in_array($order->status, [Order::CANCELLED, Order::PAYMENT_FAILED, Order::PAYMENT_EXPIRED], true)) return $order; if ($order->status === Order::FULFILLED) throw new InsufficientStockException('سفارش تکمیل‌شده قابل لغو نیست.'); $this->reservations->releaseOrder($order); $order->update(['status' => Order::CANCELLED]); return $order->refresh(); });
    }
    public function confirmPayment(Order $order): Order
    {
        return DB::transaction(function () use ($order) { $order = Order::query()->lockForUpdate()->findOrFail($order->id); if (in_array($order->status, [Order::PAID, Order::FULFILLED], true)) return $order; if ($order->status !== Order::PENDING_PAYMENT) throw new InsufficientStockException('وضعیت سفارش برای تأیید پرداخت معتبر نیست.'); $this->reservations->releaseOrder($order, StockReservation::COMMITTED); foreach ($order->items as $item) $this->inventory->deductPhysical($item->variant, $item->quantity, 'payment_confirmed', null, $order->id); $order->update(['status' => Order::PAID]); return $order->refresh(); });
    }
    public function fulfill(Order $order): Order
    {
        return DB::transaction(function () use ($order) { $order = Order::query()->lockForUpdate()->findOrFail($order->id); if ($order->status === Order::FULFILLED) return $order; if ($order->status !== Order::PAID) throw new InsufficientStockException('فقط سفارش پرداخت‌شده قابل ارسال است.'); $order->update(['status' => Order::FULFILLED]); return $order->refresh(); });
    }
    public function restockReturn(Order $order, string $reason, ?\App\Models\User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor) { $order = Order::query()->lockForUpdate()->findOrFail($order->id); if ($order->status === Order::RETURNED) return $order; if (! in_array($order->status, [Order::PAID, Order::FULFILLED], true)) throw new InsufficientStockException('این سفارش قابل ثبت به‌عنوان مرجوعی نیست.'); foreach ($order->items as $item) $this->inventory->receive($item->variant, $item->quantity, $reason, $actor); $order->update(['status' => Order::RETURNED]); return $order->refresh(); });
    }
}
