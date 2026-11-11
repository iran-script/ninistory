<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShippingRate extends Model { protected $fillable=['shipping_method_id','province_code','city_code','fee_toman','free_over_toman','weight_surcharge_toman','weight_step_grams','min_weight_grams','max_weight_grams','min_order_toman','max_order_toman','priority','effective_from','effective_until','is_enabled']; protected function casts(): array { return ['fee_toman'=>'integer','free_over_toman'=>'integer','weight_surcharge_toman'=>'integer','weight_step_grams'=>'integer','min_weight_grams'=>'integer','max_weight_grams'=>'integer','min_order_toman'=>'integer','max_order_toman'=>'integer','priority'=>'integer','effective_from'=>'datetime','effective_until'=>'datetime','is_enabled'=>'boolean']; } public function method(): BelongsTo { return $this->belongsTo(ShippingMethod::class,'shipping_method_id'); } }
