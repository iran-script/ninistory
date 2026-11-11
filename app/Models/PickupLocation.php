<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PickupLocation extends Model { protected $fillable=['code','name','province_code','city_code','address','phone','opening_hours','instructions','is_enabled','sort_order']; protected function casts(): array { return ['is_enabled'=>'boolean']; } }
