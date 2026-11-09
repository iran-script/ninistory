<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = ['product_variant_id','user_id','quantity_delta','quantity_before','quantity_after','reason','reference','movement_type','physical_before','physical_after','reserved_before','reserved_after','order_id','reservation_id','idempotency_key'];
    protected function casts(): array { return ['quantity_delta' => 'integer', 'quantity_before' => 'integer', 'quantity_after' => 'integer', 'physical_before' => 'integer', 'physical_after' => 'integer', 'reserved_before' => 'integer', 'reserved_after' => 'integer']; }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
