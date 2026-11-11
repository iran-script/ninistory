<?php
namespace App\Http\Controllers;
use App\Models\{Address, ShippingProvince};
use App\Services\ShippingAddressService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
class AddressController extends Controller {
 public function index(Request $r): View { return view('store.addresses.index',['addresses'=>$r->user()->addresses()->latest()->get(),'provinces'=>ShippingProvince::with('cities')->where('is_enabled',true)->get()]); }
 public function store(Request $r, ShippingAddressService $service): RedirectResponse { $data=$r->validate(['nickname'=>'nullable|string|max:80','recipient_name'=>'required|string|max:160','phone'=>'required|string|max:20','province_code'=>'required|string','city_code'=>'required|string','address'=>'required|string|max:1000','building'=>'nullable|string|max:120','postal_code'=>'required|string|max:20','is_default'=>'nullable|boolean']); $data=$service->validate($data); $data['user_id']=$r->user()->id; if(!empty($data['is_default'])) $r->user()->addresses()->update(['is_default'=>false]); $r->user()->addresses()->create($data); return back()->with('status','نشانی ذخیره شد.'); }
 public function update(Request $r, Address $address, ShippingAddressService $service): RedirectResponse { abort_unless($address->user_id===$r->user()->id,404); $data=$r->validate(['nickname'=>'nullable|string|max:80','recipient_name'=>'required|string|max:160','phone'=>'required|string|max:20','province_code'=>'required|string','city_code'=>'required|string','address'=>'required|string|max:1000','building'=>'nullable|string|max:120','postal_code'=>'required|string|max:20','is_default'=>'nullable|boolean']); $data=$service->validate($data); if(!empty($data['is_default'])) $r->user()->addresses()->where('id','!=',$address->id)->update(['is_default'=>false]); $address->update($data); return back()->with('status','نشانی به‌روزرسانی شد.'); }
 public function destroy(Request $r, Address $address): RedirectResponse { abort_unless($address->user_id===$r->user()->id,404); abort_if($address->user_id && $address->orders()->exists(),422,'نشانی استفاده‌شده در سفارش قابل حذف نیست.'); $address->delete(); return back()->with('status','نشانی حذف شد.'); }
}
