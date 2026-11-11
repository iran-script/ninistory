<?php
namespace App\Services;
use App\Models\{ShippingCity, ShippingProvince};
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ShippingAddressService {
    public function normalize(array $data): array { foreach ($data as $key=>$value) if (is_string($value)) $data[$key]=str_replace(['ي','ك','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],['ی','ک','0','1','2','3','4','5','6','7','8','9'],trim($value)); return $data; }
    public function validate(array $data, bool $required=true): array { $data=$this->normalize($data); if (!$required) return $data; $province=ShippingProvince::where('code',$data['province_code']??'')->where('is_enabled',true)->first(); $city=ShippingCity::where('code',$data['city_code']??'')->where('province_id',$province?->id)->where('is_enabled',true)->first(); if (!$province || !$city) throw ValidationException::withMessages(['city_code'=>'استان یا شهر انتخاب‌شده معتبر نیست.']); $postal=preg_replace('/\D+/','',$data['postal_code']??''); if (strlen($postal)!==10) throw ValidationException::withMessages(['postal_code'=>'کد پستی باید ۱۰ رقم باشد.']); return array_merge($data,['province'=>$province->name,'city'=>$city->name,'postal_code'=>$postal]); }
}
