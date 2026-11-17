<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private InventoryMovementService $movements) {}

    public function setPhysicalStock(ProductVariant $variant, int $quantity, string $reason, ?User $actor = null, string $type = 'manual_adjustment'): ProductVariant
    {
        if ($quantity < 0) {
            throw new InsufficientStockException('موجودی نمی‌تواند منفی باشد.');
        }

        return DB::transaction(function () use ($variant, $quantity, $reason, $actor, $type) {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            if ($quantity < $locked->reserved_quantity) {
                throw new InsufficientStockException('موجودی فیزیکی کمتر از موجودی رزروشده است.');
            }
            $before = (int) $locked->stock_quantity;
            if ($before === $quantity) {
                return $locked;
            }
            $locked->update(['stock_quantity' => $quantity]);
            $this->movements->record($locked, $type, $before, $quantity, $locked->reserved_quantity, $locked->reserved_quantity, $reason, $actor);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, array{quantity:int|string, updated_at?:string|null}>  $changes
     */
    public function setPhysicalStockBatch(array $changes, string $reason, ?User $actor = null): int
    {
        return DB::transaction(function () use ($changes, $reason, $actor): int {
            $updated = 0;

            foreach ($changes as $variantId => $change) {
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
                $quantity = (int) $change['quantity'];

                if ($quantity === (int) $variant->stock_quantity) {
                    continue;
                }

                if (blank($change['updated_at'] ?? null) || $variant->updated_at?->toISOString() !== $change['updated_at']) {
                    throw ValidationException::withMessages([
                        "inventory.$variantId.quantity" => 'موجودی این تنوع توسط مدیر دیگری تغییر کرده است؛ صفحه را تازه‌سازی و دوباره بررسی کنید.',
                    ]);
                }

                if ($quantity < (int) $variant->reserved_quantity) {
                    throw ValidationException::withMessages([
                        "inventory.$variantId.quantity" => 'موجودی فیزیکی نمی‌تواند کمتر از موجودی رزروشده باشد.',
                    ]);
                }

                $before = (int) $variant->stock_quantity;
                $variant->update(['stock_quantity' => $quantity]);
                $this->movements->record($variant, 'manual_adjustment', $before, $quantity, (int) $variant->reserved_quantity, (int) $variant->reserved_quantity, $reason, $actor);
                $updated++;
            }

            return $updated;
        });
    }

    public function receive(ProductVariant $variant, int $quantity, string $reason, ?User $actor = null): ProductVariant
    {
        if ($quantity < 1) {
            throw new InsufficientStockException('تعداد دریافت باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($variant, $quantity, $reason, $actor) {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $before = (int) $locked->stock_quantity;
            $after = $before + $quantity;
            $locked->update(['stock_quantity' => $after]);
            $this->movements->record($locked, 'receipt', $before, $after, $locked->reserved_quantity, $locked->reserved_quantity, $reason, $actor);

            return $locked->refresh();
        });
    }

    public function deductPhysical(ProductVariant $variant, int $quantity, string $reason, ?User $actor = null, ?int $orderId = null): ProductVariant
    {
        if ($quantity < 1) {
            throw new InsufficientStockException('تعداد کسر باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($variant, $quantity, $reason, $actor, $orderId) {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $before = (int) $locked->stock_quantity;
            if ($before - $quantity < $locked->reserved_quantity) {
                throw new InsufficientStockException('موجودی فیزیکی کافی نیست.');
            }
            $after = $before - $quantity;
            $locked->update(['stock_quantity' => $after]);
            $this->movements->record($locked, 'sale_fulfillment', $before, $after, $locked->reserved_quantity, $locked->reserved_quantity, $reason, $actor, null, $orderId);

            return $locked->refresh();
        });
    }
}
