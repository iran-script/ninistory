<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = ['product_variant_id','user_id','quantity_delta','quantity_before','quantity_after','reason','reference'];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
