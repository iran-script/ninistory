<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrderEvent extends Model { protected $fillable = ['order_id','actor_id','event_type','previous_state','new_state','reason','idempotency_key']; protected function casts(): array { return ['previous_state'=>'array','new_state'=>'array']; } public function order(): BelongsTo { return $this->belongsTo(Order::class); } public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); } }
