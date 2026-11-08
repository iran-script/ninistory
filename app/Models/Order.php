<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['number','user_id','recipient_name','phone','address','postal_code','currency','subtotal_toman','discount_toman','shipping_toman','status'];
    protected function casts(): array { return ['subtotal_toman'=>'integer','discount_toman'=>'integer','shipping_toman'=>'integer']; }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
