<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
class ReturnRequest extends Model { protected $table = 'returns'; protected $fillable = ['order_id','user_id','approved_by','inspected_by','status','reason','admin_note','approved_at','received_at','inspected_at','closed_at']; protected function casts(): array { return ['approved_at'=>'datetime','received_at'=>'datetime','inspected_at'=>'datetime','closed_at'=>'datetime']; } public function items(): HasMany { return $this->hasMany(ReturnItem::class, 'return_id'); } public function order(): BelongsTo { return $this->belongsTo(Order::class); } public function requester(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); } public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); } public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspected_by'); } }
