<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const REQUESTED = 'requested'; public const PENDING = 'pending'; public const SUCCEEDED = 'succeeded'; public const FAILED = 'failed'; public const REQUIRES_RECONCILIATION = 'requires_reconciliation';
    protected $fillable = ['order_id','payment_id','requested_by','amount_toman','currency','reason','status','provider_reference','idempotency_key','failure_reason','metadata','completed_at'];
    protected function casts(): array { return ['amount_toman'=>'integer','metadata'=>'array','completed_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
