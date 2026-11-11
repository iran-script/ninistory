<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const CREATED = 'created'; public const PENDING = 'pending'; public const SUCCEEDED = 'succeeded'; public const FAILED = 'failed'; public const CANCELLED = 'cancelled'; public const EXPIRED = 'expired'; public const REQUIRES_RECONCILIATION = 'requires_reconciliation'; public const REFUNDED = 'refunded'; public const PARTIALLY_REFUNDED = 'partially_refunded';
    protected $fillable = ['payment_uuid','order_id','provider','currency','provider_reference','provider_authority','idempotency_key','amount_toman','amount_rials','status','initiated_at','expires_at','verified_at','failure_code','failure_reason','attempt_number','provider_metadata','reconciliation_note'];
    protected function casts(): array { return ['amount_toman'=>'integer','amount_rials'=>'integer','attempt_number'=>'integer','initiated_at'=>'datetime','expires_at'=>'datetime','verified_at'=>'datetime','provider_metadata'=>'array']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Refund::class); }
    public function getRouteKeyName(): string { return 'payment_uuid'; }
}
