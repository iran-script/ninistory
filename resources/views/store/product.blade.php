@extends('layouts.store')
@section('title', $product->name.' | نینی‌استوری')
@section('content')
@php
    $validImages = $product->images->filter(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->path));
    $colors = $product->variants->where('is_active', true)->unique('color_name')->values();
    $sizes = $product->variants->where('is_active', true)->unique('size')->values();
@endphp
<section class="product-detail mx-auto grid max-w-[1440px] gap-8 px-4 py-6 md:grid-cols-[1.05fr_.95fr] md:gap-12 md:px-8 md:py-10" data-product-configurator>
    <div class="min-w-0">
        <div class="product-detail__main-image relative aspect-square overflow-hidden rounded-3xl bg-[#f8eeeb] md:aspect-[.95/1]" data-gallery-main><x-product-media :product="$product" :image="$validImages->first()" loading="eager" /></div>
        @if ($validImages->count() > 1)<div class="mt-3 flex gap-3 overflow-x-auto" aria-label="تصاویر محصول">@foreach ($validImages as $image)<button type="button" class="product-thumbnail h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-[var(--color-border)] bg-white" data-gallery-image="{{ asset('storage/'.$image->path) }}" data-gallery-alt="{{ $image->alt_text ?: $product->name }}" aria-label="نمایش تصویر {{ $loop->iteration }}"><img class="h-full w-full object-contain" src="{{ asset('storage/'.$image->path) }}" alt="" loading="lazy"></button>@endforeach</div>@endif
    </div>
    <div class="md:py-4"><nav class="mb-5 text-sm text-[var(--color-muted)]"><a href="{{ route('home') }}">خانه</a> / <a href="{{ route('products.index') }}">محصولات</a> / {{ $product->category?->name }}</nav><h1 class="text-2xl font-extrabold text-[var(--color-wine)] md:text-4xl">{{ $product->name }}</h1><p class="mt-3 leading-8 text-[#6e6263]">{{ $product->description ?: $product->short_description }}</p>
        <div class="mt-5 flex items-end gap-3"><p class="text-2xl font-extrabold text-[var(--color-action)]" data-variant-price>—</p><del class="text-sm text-[var(--color-muted)]" data-variant-compare></del></div>
        <form action="{{ route('cart.add') }}" method="post" class="mt-6 space-y-5">@csrf
            <div class="product-choice"><span class="block font-bold">رنگ</span><div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="انتخاب رنگ">@foreach($colors as $color)<button type="button" class="choice-chip choice-chip--color" data-color="{{ $color->color_name }}" style="--swatch: {{ $color->color_hex ?: '#F6B8C8' }}">{{ $color->color_name }}</button>@endforeach</div></div>
            <div class="product-choice"><span class="block font-bold">سایز</span><div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="انتخاب سایز">@foreach($sizes as $size)<button type="button" class="choice-chip" data-size="{{ $size->size }}">{{ $size->size }}</button>@endforeach</div></div>
            <label class="block font-bold">ترکیب واقعی محصول<select required name="variant_id" data-variant-select class="mt-2 block w-full rounded-xl border border-[var(--color-border)] bg-white p-3">@foreach($product->variants as $variant)<option value="{{ $variant->id }}" data-color="{{ $variant->color_name }}" data-size="{{ $variant->size }}" data-price="{{ $variant->price_toman }}" data-compare="{{ $variant->compare_at_price_toman }}" data-stock="{{ $variant->available_quantity }}" data-active="{{ $variant->is_active ? '1' : '0' }}" @disabled(!$variant->is_active)>{{ $variant->color_name }} · {{ $variant->size }}{{ $variant->available_quantity < 1 ? ' · ناموجود' : '' }}</option>@endforeach</select></label>
            <p class="text-sm font-semibold text-emerald-700" data-variant-stock role="status">در حال بررسی موجودی…</p><label class="block font-bold">تعداد<input name="quantity" type="number" min="1" max="10" value="1" class="mt-2 block min-h-12 w-28 rounded-xl border border-[var(--color-border)] p-3"></label>
            @error('variant_id')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror<button type="submit" class="min-h-14 w-full rounded-full bg-[var(--color-action)] font-bold text-white shadow-lg shadow-pink-900/10 disabled:cursor-not-allowed disabled:opacity-50">افزودن به سبد خرید</button>
        </form><div class="mt-6 grid grid-cols-3 rounded-2xl border border-[var(--color-border)] bg-white p-4 text-center text-xs"><span>ارسال سراسر ایران</span><span>پرداخت امن</span><span>۷ روز بازگشت</span></div><div class="mt-6 rounded-2xl bg-white p-5"><h2 class="font-bold">روش نگهداری</h2><p class="mt-2">{{ $product->care_instructions ?: 'با آب سرد و لباس‌های هم‌رنگ بشویید.' }}</p></div>
    </div>
</section>
@push('scripts')<script>document.querySelectorAll('[data-gallery-image]').forEach((button)=>button.addEventListener('click',()=>{const main=document.querySelector('[data-gallery-main] img');if(main){main.src=button.dataset.galleryImage;main.alt=button.dataset.galleryAlt}}));</script>@endpush
@endsection
