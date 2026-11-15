<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'size', 'color_name', 'color_hex', 'price_toman', 'compare_at_price_toman', 'stock_quantity', 'reserved_quantity', 'image_path', 'weight_grams', 'is_active', 'archived_at'];

    protected function casts(): array
    {
        return ['price_toman' => 'integer', 'compare_at_price_toman' => 'integer', 'weight_grams' => 'integer', 'stock_quantity' => 'integer', 'reserved_quantity' => 'integer', 'is_active' => 'boolean', 'archived_at' => 'datetime'];
    }

    protected $appends = ['available_quantity'];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget('seo:sitemap:products'));
        static::deleted(fn (): bool => Cache::forget('seo:sitemap:products'));
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_quantity > 0 && $this->available_quantity <= config('inventory.low_stock_threshold', 5);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
