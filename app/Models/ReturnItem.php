<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReturnItem extends Model { protected $fillable = ['return_id','order_item_id','quantity','disposition','inspection_note']; protected function casts(): array { return ['quantity'=>'integer']; } public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); } }
