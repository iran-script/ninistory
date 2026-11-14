@extends('admin.layout')
@section('content')
<div class="space-y-5" dir="rtl">
    <h1 class="text-2xl font-bold">روش‌ها و قواعد ارسال</h1>
    <form method="post" action="{{ route('admin.shipping-methods.store') }}" class="grid gap-3 rounded-2xl bg-white p-4 md:grid-cols-5">@csrf
        <input name="code" placeholder="کد داخلی" required class="rounded border p-2"><input name="name" placeholder="نام فارسی" required class="rounded border p-2"><input name="base_fee_toman" type="number" min="0" placeholder="هزینه پایه تومان" required class="rounded border p-2"><input name="estimated_delivery" placeholder="زمان تحویل" class="rounded border p-2"><input name="sort_order" type="number" value="1" class="rounded border p-2"><button class="rounded bg-slate-900 p-2 text-white">افزودن روش</button>
    </form>
    @foreach($methods as $method)
        <section class="rounded-2xl bg-white p-4">
            <form method="post" action="{{ route('admin.shipping-methods.update',$method) }}" class="grid gap-3 md:grid-cols-6">@csrf @method('put')
                <b>{{ $method->code }}</b><input name="name" value="{{ $method->name }}" class="rounded border p-2"><input name="base_fee_toman" type="number" min="0" value="{{ $method->base_fee_toman }}" class="rounded border p-2"><input name="estimated_delivery" value="{{ $method->estimated_delivery }}" class="rounded border p-2"><label><input type="checkbox" name="is_enabled" value="1" @checked($method->is_enabled)> فعال</label><button class="rounded bg-slate-100 p-2">ذخیره روش</button>
            </form>
            <h2 class="mt-4 font-bold">قواعد نرخ</h2>
            @foreach($method->rates as $rate)
                <div class="mt-2 rounded-xl border-t pt-2 text-sm">
                    <form method="post" action="{{ route('admin.shipping-rates.update',$rate) }}" class="grid gap-2 md:grid-cols-8">@csrf @method('put')
                        <input name="province_code" value="{{ $rate->province_code }}" placeholder="کد استان" class="rounded border p-2"><input name="city_code" value="{{ $rate->city_code }}" placeholder="کد شهر" class="rounded border p-2"><input required name="fee_toman" type="number" min="0" value="{{ $rate->fee_toman }}" class="rounded border p-2"><input name="free_over_toman" type="number" min="0" value="{{ $rate->free_over_toman }}" placeholder="ارسال رایگان بالای" class="rounded border p-2"><input name="priority" type="number" min="0" value="{{ $rate->priority }}" class="rounded border p-2"><input name="weight_surcharge_toman" type="number" min="0" value="{{ $rate->weight_surcharge_toman }}" placeholder="اضافه وزن" class="rounded border p-2"><label class="flex items-center gap-1"><input type="checkbox" name="is_enabled" value="1" @checked($rate->is_enabled)> فعال</label><button class="rounded bg-slate-100 p-2">ویرایش قاعده</button>
                    </form>
                    <form method="post" action="{{ route('admin.shipping-rates.destroy',$rate) }}" class="mt-1">@csrf @method('delete')<button class="text-red-700">حذف قاعده</button></form>
                </div>
            @endforeach
            <form method="post" action="{{ route('admin.shipping-rates.store',$method) }}" class="mt-3 grid gap-2 md:grid-cols-7">@csrf
                <input name="province_code" placeholder="کد استان" class="rounded border p-2"><input name="city_code" placeholder="کد شهر" class="rounded border p-2"><input required name="fee_toman" type="number" min="0" placeholder="هزینه" class="rounded border p-2"><input name="free_over_toman" type="number" min="0" placeholder="ارسال رایگان بالای" class="rounded border p-2"><input name="priority" type="number" min="0" value="0" placeholder="اولویت" class="rounded border p-2"><input name="weight_surcharge_toman" type="number" min="0" value="0" placeholder="اضافه وزن" class="rounded border p-2"><button class="rounded bg-slate-100 p-2">افزودن قاعده</button>
            </form>
        </section>
    @endforeach
</div>
@endsection
