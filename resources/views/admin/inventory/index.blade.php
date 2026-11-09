@extends('admin.layout')
@section('title', 'مدیریت موجودی')
@section('content')
<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">مدیریت موجودی</h1><p class="text-sm text-slate-500">موجودی قابل فروش = موجودی فیزیکی − رزروشده</p></div>
    <form class="grid gap-3 rounded-2xl bg-white p-4 md:grid-cols-5">
        <input name="sku" value="{{ request('sku') }}" placeholder="SKU" class="rounded-xl border p-2">
        <input name="product" value="{{ request('product') }}" placeholder="محصول" class="rounded-xl border p-2">
        <select name="category_id" class="rounded-xl border p-2"><option value="">همه دسته‌ها</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select>
        <label class="flex items-center gap-2"><input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock'))> کم‌موجودی</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="out_of_stock" value="1" @checked(request()->boolean('out_of_stock'))> ناموجود</label>
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-white">فیلتر</button>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white"><table class="w-full text-right text-sm"><thead><tr class="border-b"><th class="p-3">محصول / SKU</th><th class="p-3">رنگ / اندازه</th><th class="p-3">فیزیکی</th><th class="p-3">رزروشده</th><th class="p-3">قابل فروش</th><th class="p-3">عملیات</th></tr></thead><tbody>
    @foreach($variants as $variant)<tr class="border-b"><td class="p-3">{{ $variant->product->name }}<div class="text-xs text-slate-500">{{ $variant->sku }}</div></td><td class="p-3">{{ $variant->color_name }} / {{ $variant->size }}</td><td class="p-3">{{ $variant->stock_quantity }}</td><td class="p-3">{{ $variant->reserved_quantity }}</td><td class="p-3"><span class="{{ $variant->available_quantity === 0 ? 'text-red-700' : ($variant->is_low_stock ? 'text-amber-700' : 'text-emerald-700') }}">{{ $variant->available_quantity }}</span></td><td class="space-y-2 p-3"><form method="post" action="{{ route('admin.inventory.receive', $variant) }}" class="flex gap-2">@csrf<input name="quantity" type="number" min="1" placeholder="تعداد" class="w-20 rounded border"><input name="reason" required placeholder="دلیل" class="w-32 rounded border"><button class="rounded bg-emerald-600 px-2 py-1 text-white">دریافت</button></form><a class="text-blue-700" href="{{ route('admin.inventory.movements', $variant) }}">تاریخچه</a></td></tr>@endforeach
    </tbody></table></div>
    {{ $variants->links() }}
</div>
@endsection
