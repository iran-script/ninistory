@extends('layouts.store')
@section('title', $product->name.' | نینی‌استوری')
@section('content')
@php($previewKey = str_contains($product->slug, 'sunny') ? 'floral-dress' : (str_contains($product->slug, 'cotton') ? 'teddy-sweater' : 'pink-coat'))
@php($previewOnly = app()->environment(['local', 'testing']) && $product->images->isEmpty())
<section class="mx-auto grid max-w-[1440px] gap-6 px-4 py-6 md:grid-cols-[1.05fr_.95fr] md:gap-12 md:px-8 md:py-10">
    <div class="min-w-0">
        <div class="relative aspect-[4/4.2] overflow-hidden rounded-3xl bg-[#f8eeeb] md:aspect-[.95/1]">
            @if($product->images->isNotEmpty())<img class="h-full w-full object-cover" src="{{ asset('storage/'.$product->images->first()->path) }}" alt="{{ $product->images->first()->alt_text }}">
            @elseif($previewOnly)<img class="h-full w-full object-cover" src="{{ route('preview.asset', ['path'=>'images/products/'.$previewKey.'.jpg']) }}" alt="تصویر نمایشی؛ با کالای واقعی تطبیق ندارد"><span class="absolute bottom-3 start-3 rounded-full bg-white/90 px-3 py-1 text-xs">تصویر پیش‌نمایش محلی</span>
            @else<div class="grid h-full place-items-center text-sm text-[var(--color-muted)]">تصویر محصول به‌زودی</div>@endif
        </div>
        @if($product->images->count() > 1)<div class="mt-3 flex gap-3 overflow-x-auto">@foreach($product->images as $image)<img class="h-20 w-20 shrink-0 rounded-xl object-cover" src="{{ asset('storage/'.$image->path) }}" alt="{{ $image->alt_text }}">@endforeach</div>@endif
    </div>
    <div class="md:py-8"><nav class="mb-5 text-sm text-[var(--color-muted)]"><a href="{{ route('home') }}">خانه</a> / <a href="{{ route('products.index') }}">محصولات</a> / {{ $product->category?->name }}</nav><h1 class="text-2xl font-extrabold text-[var(--color-wine)] md:text-4xl">{{ $product->name }}</h1><p class="mt-4 leading-8 text-[#6e6263]">{{ $product->description }}</p>
        @if($product->variants->isNotEmpty())<p class="mt-5 text-2xl font-extrabold text-[var(--color-action)]">{{ number_format($product->variants->min('price_toman')) }} <span class="text-sm">تومان</span></p>@endif
        <form action="{{ route('cart.add') }}" method="post" class="mt-6">@csrf<label class="block font-bold">انتخاب رنگ و سایز<select required name="variant_id" class="mt-2 block w-full rounded-xl border border-[var(--color-border)] bg-white p-3"><option value="">یک گزینه انتخاب کن</option>@foreach($product->variants as $variant)<option value="{{ $variant->id }}" @disabled(!$variant->is_active || $variant->stock_quantity < 1)>{{ $variant->color_name }} · {{ $variant->size }} · {{ number_format($variant->price_toman) }} تومان {{ $variant->stock_quantity < 1 ? '(ناموجود)' : '' }}</option>@endforeach</select></label><label class="mt-4 block font-bold">تعداد<input name="quantity" type="number" min="1" max="10" value="1" class="mt-2 block min-h-12 w-28 rounded-xl border border-[var(--color-border)] p-3"></label>@error('variant_id')<p class="mt-2 text-red-700" role="alert">{{ $message }}</p>@enderror<button class="mt-5 min-h-14 w-full rounded-full bg-[var(--color-action)] font-bold text-white shadow-lg shadow-pink-900/10">افزودن به سبد خرید</button></form>
        <div class="mt-6 grid grid-cols-3 rounded-2xl border border-[var(--color-border)] bg-white p-4 text-center text-xs"><span>ارسال سراسر ایران</span><span>پرداخت امن</span><span>۷ روز بازگشت</span></div><div class="mt-6 rounded-2xl bg-white p-5"><h2 class="font-bold">روش نگهداری</h2><p class="mt-2">{{ $product->care_instructions }}</p></div>
    </div>
</section>
@endsection
