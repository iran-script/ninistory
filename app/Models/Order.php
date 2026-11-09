<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const PENDING_PAYMENT = 'pending_payment';
    public const PAID = 'paid';
    public const PAYMENT_FAILED = 'payment_failed';
    public const PAYMENT_EXPIRED = 'payment_expired';
    public const CANCELLED = 'cancelled';
    public const FULFILLED = 'fulfilled';
    public const RETURNED = 'returned';
    protected $fillable = ['number','checkout_key','user_id','recipient_name','phone','address','postal_code','currency','subtotal_toman','discount_toman','shipping_toman','status'];
    protected function casts(): array { return ['subtotal_toman'=>'integer','discount_toman'=>'integer','shipping_toman'=>'integer']; }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function reservations(): HasMany { return $this->hasMany(StockReservation::class); }
}
