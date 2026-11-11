<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Shipment extends Model { protected $fillable = ['order_id','carrier','shipping_method','tracking_code','idempotency_key','status','shipped_at','delivered_at','internal_notes']; protected function casts(): array { return ['shipped_at'=>'datetime','delivered_at'=>'datetime']; } public function order(): BelongsTo { return $this->belongsTo(Order::class); } }
