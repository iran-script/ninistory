<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'size', 'color_name', 'color_hex', 'price_toman', 'compare_at_price_toman', 'stock_quantity', 'image_path', 'weight_grams', 'is_active'];
    protected function casts(): array { return ['price_toman' => 'integer', 'compare_at_price_toman' => 'integer', 'weight_grams' => 'integer', 'stock_quantity' => 'integer', 'is_active' => 'boolean']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function inventoryMovements(): HasMany { return $this->hasMany(InventoryMovement::class); }
    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
}
