<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShippingCity extends Model { protected $fillable=['province_id','code','name','is_enabled']; protected function casts(): array { return ['is_enabled'=>'boolean']; } public function province(): BelongsTo { return $this->belongsTo(ShippingProvince::class,'province_id'); } }
