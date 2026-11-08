@extends('layouts.store')
@section('title', 'نینی‌استوری | قصه‌ی خوش‌پوشی')
@section('content')
@php($previewOnly = app()->environment(['local', 'testing']))
<div class="mx-auto max-w-[1500px] px-3 pt-4 md:px-6 md:pt-5">
    <section class="relative isolate overflow-hidden rounded-[24px] bg-[#f8dfe5] shadow-sm md:rounded-[28px]">
        @if($previewOnly)
            <img class="absolute inset-0 -z-10 h-full w-full object-cover object-[65%_center]" src="{{ route('preview.asset', ['path'=>'images/banners/hero-desktop.jpg']) }}" alt="" fetchpriority="high">
            <span class="absolute bottom-2 start-3 rounded-full bg-white/90 px-2 py-1 text-[10px] text-[#725b5a]">تصویر نمایشی</span>
        @else
            <div class="absolute inset-0 -z-10 bg-gradient-to-l from-[#fff4f2] via-[#f9dce4] to-[#f1d9ed]"></div>
        @endif
        <div class="flex min-h-[205px] items-center p-5 sm:min-h-[250px] sm:p-8 md:min-h-[292px] md:justify-start md:px-12">
            <div class="max-w-[48%] rounded-2xl bg-white/75 p-3 backdrop-blur-sm sm:max-w-[43%] sm:p-5 md:max-w-[38%] md:bg-white/65 md:p-7">
                <p class="text-[10px] font-bold text-[var(--color-action)] sm:text-xs md:text-sm">مجموعه‌ی تازه‌ی نینی‌استوری</p>
                <h1 class="mt-1 text-lg font-extrabold leading-relaxed text-[#662a3e] sm:text-2xl md:text-4xl">قصه‌های قشنگ<br>برای کوچولوها</h1>
                <p class="mt-1 hidden text-xs leading-6 text-[#634c50] sm:block md:text-base">لباس‌های نرم و دوست‌داشتنی برای روزهای شیرین کودکی</p>
                <a href="{{ route('products.index') }}" class="mt-2 inline-flex min-h-9 items-center rounded-full bg-[var(--color-action)] px-4 text-[10px] font-bold text-white shadow-md sm:mt-4 sm:min-h-11 sm:px-6 sm:text-sm">مشاهده کالکشن جدید <span class="ms-2" aria-hidden="true">←</span></a>
            </div>
        </div>
    </section>
</div>

<section class="mx-auto max-w-[1500px] px-4 py-6 md:px-6 md:py-7">
    <div class="mb-4 flex items-end justify-between"><div><p class="text-[11px] text-[var(--color-muted)] md:text-sm">برای هر کوچولوی دوست‌داشتنی</p><h2 class="mt-1 text-lg font-extrabold text-[var(--color-wine)] md:text-2xl">دسته‌بندی‌ها</h2></div><a class="text-xs font-bold text-[var(--color-action)] md:text-sm" href="{{ route('products.index') }}">مشاهده همه</a></div>
    <div class="grid grid-cols-3 gap-2.5 md:grid-cols-4 md:gap-5">
        @foreach($categories as $category)
            @php($categoryKey = match($category->slug) {'girls'=>'girls','boys'=>'boys',default=>'newborn'})
            <a href="{{ route('products.index', ['category'=>$category->id]) }}" class="relative flex min-h-[82px] items-center overflow-hidden rounded-2xl bg-[#f8e5eb] shadow-sm sm:min-h-[108px] md:min-h-[132px]">
                @if($previewOnly)<img class="absolute inset-0 h-full w-full object-cover" src="{{ route('preview.asset', ['path'=>'images/categories/'.$categoryKey.'.jpg']) }}" alt="{{ $category->name }}؛ تصویر نمایشی" loading="lazy">@else<div class="absolute inset-0 bg-gradient-to-l from-[#f8e5eb] to-[#f4dfe5]"></div><span class="relative z-10 ms-auto rounded-s-xl bg-white/75 px-2 py-2 text-xs font-extrabold text-[#552a39] backdrop-blur-sm sm:px-4 sm:text-base md:text-lg">{{ $category->name }}</span>@endif
            </a>
        @endforeach
        <a href="{{ route('products.index') }}" class="relative hidden min-h-[132px] items-center overflow-hidden rounded-2xl bg-[#fff0e3] shadow-sm md:flex">
            @if($previewOnly)<img class="absolute inset-0 h-full w-full object-cover" src="{{ route('preview.asset', ['path'=>'images/banners/autumn-collection.jpg']) }}" alt="کالکشن پاییزی؛ تصویر نمایشی" loading="lazy">@endif
            <span class="relative z-10 ms-auto rounded-s-xl bg-white/80 px-4 py-3 text-lg font-extrabold text-[#552a39]">مجموعه‌ی فصلی</span>
        </a>
    </div>
</section>

<section class="mx-auto hidden max-w-[1460px] grid-cols-4 gap-3 rounded-2xl bg-[#fcf6f7] px-5 py-4 text-center text-sm font-semibold text-[#49383b] md:grid"><div class="flex items-center justify-center gap-2"><span class="text-xl text-[var(--color-action)]">♧</span>ارسال سریع سراسر ایران</div><div class="flex items-center justify-center gap-2"><span class="text-xl text-[var(--color-action)]">♡</span>ضمانت اصالت کالا</div><div class="flex items-center justify-center gap-2"><span class="text-xl text-[var(--color-action)]">↺</span>۷ روز امکان بازگشت</div><div class="flex items-center justify-center gap-2"><span class="text-xl text-[var(--color-action)]">⌁</span>راهنمای انتخاب سایز</div></section>

<section class="mx-auto max-w-[1500px] px-4 py-7 md:px-6 md:py-9">
    <div class="mb-4 flex items-end justify-between"><div><p class="text-[11px] text-[var(--color-muted)] md:text-sm">انتخاب‌های محبوب</p><h2 class="mt-1 text-xl font-extrabold text-[var(--color-wine)] md:text-2xl">پرفروش‌ها</h2></div><a href="{{ route('products.index') }}" class="text-xs font-bold text-[var(--color-action)] md:text-sm">مشاهده همه ←</a></div>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5">@forelse($featured as $product)<x-product-card :product="$product"/>@empty<p class="col-span-full rounded-xl bg-white p-8 text-center">به‌زودی محصولات تازه اضافه می‌شوند.</p>@endforelse</div>
</section>

@if($previewOnly)
<section class="mx-auto max-w-[1500px] px-4 md:px-6"><a href="{{ route('products.index') }}" class="block overflow-hidden rounded-3xl"><img src="{{ route('preview.asset', ['path'=>'images/banners/autumn-collection.jpg']) }}" alt="کالکشن پاییزی؛ تصویر نمایشی" class="h-40 w-full object-cover sm:h-52 md:h-64" loading="lazy"></a></section>
@endif
<section id="club" class="mx-auto mb-9 mt-4 grid max-w-[1500px] gap-3 px-4 sm:grid-cols-2 md:px-6">
    <a href="{{ route('products.index') }}" class="relative flex min-h-[120px] items-center overflow-hidden rounded-3xl bg-[#fde4ed] md:min-h-[176px]">@if($previewOnly)<img src="{{ route('preview.asset', ['path'=>'images/banners/club.jpg']) }}" alt="باشگاه مشتریان؛ تصویر نمایشی" class="absolute inset-0 h-full w-full object-cover" loading="lazy">@else<span class="p-5 text-xl font-extrabold">باشگاه قصه‌ها</span>@endif</a>
    <a href="{{ route('products.index') }}" class="relative flex min-h-[120px] items-center overflow-hidden rounded-3xl bg-[#fff0e3] md:min-h-[176px]">@if($previewOnly)<img src="{{ route('preview.asset', ['path'=>'images/banners/size-guide.jpg']) }}" alt="راهنمای اندازه؛ تصویر نمایشی" class="absolute inset-0 h-full w-full object-cover" loading="lazy">@else<span class="p-5 text-xl font-extrabold">راهنمای اندازه</span>@endif</a>
</section>
@endsection
