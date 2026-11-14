<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InventoryReservationService
{
    public function __construct(private InventoryMovementService $movements) {}

    public function reserveOrder(Order $order, iterable $items, string $checkoutKey): void
    {
        $items = collect($items)->map(fn ($item) => ['variant' => $item['variant'] ?? $item->variant, 'quantity' => (int) ($item['quantity'] ?? $item->quantity)])->sortBy(fn ($item) => $item['variant']->id)->values();
        DB::transaction(function () use ($items, $order, $checkoutKey): void {
            foreach ($items as $item) {
                if ($item['quantity'] < 1) {
                    throw new InsufficientStockException('تعداد کالا معتبر نیست.');
                }
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($item['variant']->id);
                $idempotencyKey = $checkoutKey.':'.$variant->id;
                $existing = StockReservation::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->order_id !== (int) $order->id || (int) $existing->quantity !== (int) $item['quantity']) {
                        throw new InsufficientStockException('کلید رزرو قبلاً برای سفارش دیگری استفاده شده است.');
                    }

                    continue;
                }
                $available = (int) $variant->stock_quantity - (int) $variant->reserved_quantity;
                if ($available < $item['quantity']) {
                    throw new InsufficientStockException('موجودی یکی از کالاها کافی نیست.');
                }
                $before = (int) $variant->reserved_quantity;
                $variant->update(['reserved_quantity' => $before + $item['quantity']]);
                $reservation = StockReservation::create([
                    'product_variant_id' => $variant->id,
                    'order_id' => $order->id,
                    'quantity' => $item['quantity'],
                    'status' => StockReservation::ACTIVE,
                    'expires_at' => CarbonImmutable::now()->addMinutes(config('inventory.reservation_ttl_minutes', 15)),
                    'idempotency_key' => $idempotencyKey,
                ]);
                $this->movements->record($variant, 'reservation_created', $variant->stock_quantity, $variant->stock_quantity, $before, $variant->reserved_quantity, 'checkout_reservation', null, $checkoutKey, $order->id, $reservation->id, $idempotencyKey);
            }
        });
    }

    public function releaseOrder(Order $order, string $status = StockReservation::RELEASED): void
    {
        DB::transaction(function () use ($order, $status) {
            $reservations = StockReservation::query()->where('order_id', $order->id)->where('status', StockReservation::ACTIVE)->orderBy('product_variant_id')->lockForUpdate()->get();
            foreach ($reservations as $reservation) {
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($reservation->product_variant_id);
                $before = (int) $variant->reserved_quantity;
                $after = $before - $reservation->quantity;
                if ($after < 0) {
                    throw new InsufficientStockException('ناسازگاری موجودی رزروشده شناسایی شد.');
                }
                $variant->update(['reserved_quantity' => $after]);
                $reservation->update(['status' => $status, 'released_at' => now()]);
                $this->movements->record($variant, $status === StockReservation::EXPIRED ? 'reservation_expired' : 'reservation_released', $variant->stock_quantity, $variant->stock_quantity, $before, $after, $status, null, $reservation->idempotency_key, $order->id, $reservation->id, $reservation->idempotency_key.':'.$status);
            }
        });
    }

    public function expireStale(): int
    {
        $count = 0;
        StockReservation::query()->where('status', StockReservation::ACTIVE)->where('expires_at', '<=', now())->orderBy('id')->pluck('order_id')->unique()->each(function ($orderId) use (&$count) {
            $order = Order::find($orderId);
            if ($order && $order->status === Order::PENDING_PAYMENT) {
                $this->releaseOrder($order, StockReservation::EXPIRED);
                $order->update(['status' => Order::PAYMENT_EXPIRED, 'payment_status' => Order::PAYMENT_EXPIRED_STATUS, 'fulfillment_status' => Order::FULFILLMENT_CANCELLED, 'cancelled_at' => now()]);
                $count++;
            }
        });

        return $count;
    }
}
