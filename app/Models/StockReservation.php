<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    public const ACTIVE = 'active';
    public const COMMITTED = 'committed';
    public const RELEASED = 'released';
    public const EXPIRED = 'expired';

    protected $fillable = ['product_variant_id', 'order_id', 'quantity', 'status', 'expires_at', 'idempotency_key', 'committed_at', 'released_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime', 'committed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
