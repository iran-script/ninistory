<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ShippingProvince extends Model { protected $fillable=['code','name','is_enabled']; protected function casts(): array { return ['is_enabled'=>'boolean']; } public function cities(): HasMany { return $this->hasMany(ShippingCity::class,'province_id'); } }
