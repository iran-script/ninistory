<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id','provider','provider_reference','amount_rials','status'];
    protected function casts(): array { return ['amount_rials'=>'integer']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
