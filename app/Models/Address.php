<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $fillable = ['user_id','nickname','recipient_name','phone','province','city','province_code','city_code','address','building','postal_code','is_default'];
    protected function casts(): array { return ['is_default'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'shipping_address_id'); }
}
