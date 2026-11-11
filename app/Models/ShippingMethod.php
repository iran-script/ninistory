<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ShippingMethod extends Model { protected $fillable=['code','name','description','is_enabled','sort_order','base_fee_toman','estimated_delivery','max_weight_grams','min_order_toman','max_order_toman','free_shipping_eligible','requires_address','configuration']; protected function casts(): array { return ['is_enabled'=>'boolean','free_shipping_eligible'=>'boolean','requires_address'=>'boolean','base_fee_toman'=>'integer','max_weight_grams'=>'integer','min_order_toman'=>'integer','max_order_toman'=>'integer','configuration'=>'array']; } public function rates(): HasMany { return $this->hasMany(ShippingRate::class); } }
