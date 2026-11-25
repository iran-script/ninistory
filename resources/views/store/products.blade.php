@extends('layouts.store')
@section('title', 'لباس‌های کودک | کیدوکی')
@section('content')
<section class="mx-auto max-w-[1500px] px-4 py-6 md:px-6">
    @if(isset($category))
        <nav class="mb-4 text-sm text-[var(--color-muted)]"><a href="{{ route('home') }}">خانه</a> / {{ $category->name }}</nav>
        @if($category->seo_content)<div class="mb-5 rounded-2xl bg-white p-5 leading-8 text-slate-700">{{ $category->seo_content }}</div>@endif
    @endif
    <div class="catalog-intro">
        <p class="flex items-center gap-2 text-sm text-[var(--color-muted)]">خانه <x-icon name="chevron-left" size="14" /> پوشاک کودک</p>
        <h1 class="mt-3 text-2xl font-extrabold md:text-3xl">لباس‌های کودک</h1>
        <p class="mt-2">انتخاب‌های لطیف برای قصه‌های هر روز</p>
    </div>
    <button type="button" class="mb-4 flex w-full items-center justify-between rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 font-bold shadow-sm md:hidden" data-filter-open><span>فیلترها</span><span aria-hidden="true">☷</span></button>
    <div class="catalog-filter-backdrop hidden" data-filter-close></div>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <form id="filters" class="catalog-filters grid grid-cols-2 gap-3 rounded-2xl border border-[var(--color-border)] bg-white p-4 shadow-sm lg:sticky lg:top-44 lg:w-[280px] lg:shrink-0 lg:grid-cols-1 lg:gap-5 lg:p-5">
            <div class="col-span-2 flex items-center justify-between lg:col-span-1"><h2 class="text-lg font-bold">فیلتر محصولات</h2><a class="text-xs font-bold text-pink-600" href="{{ route('products.index') }}">پاک کردن همه</a></div>
            <button type="button" class="col-span-2 flex items-center gap-2 text-start text-xs font-bold text-pink-600 md:hidden" data-filter-close>بستن فیلترها <x-icon name="close" size="15" /></button>
            <label class="col-span-2 text-sm font-semibold lg:col-span-1">جست‌وجو<input name="q" value="{{ request('q') }}" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal" placeholder="مثلاً ست پسرانه ۴۵"></label>
            <label class="text-sm font-semibold">دسته‌بندی<select name="category" data-tom-select class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"><option value="">همه دسته‌ها</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">مرتب‌سازی<select name="sort" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"><option value="relevant" @selected(request('sort', 'relevant') === 'relevant')>مرتبط‌ترین</option><option value="newest" @selected(request('sort')==='newest')>جدیدترین</option><option value="price_asc" @selected(request('sort')==='price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(request('sort')==='price_desc')>گران‌ترین</option></select></label>
            <fieldset class="col-span-2 lg:col-span-1"><legend class="mb-2 text-sm font-semibold">سایز</legend><div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto">@forelse($facetSizes as $size)<label class="cursor-pointer rounded-full border px-3 py-1.5 text-sm has-[:checked]:border-pink-400 has-[:checked]:bg-pink-50"><input class="sr-only" type="checkbox" name="size[]" value="{{ $size }}" @checked(in_array($size, $selectedSizes, true))>{{ $size }}</label>@empty<span class="text-sm text-slate-500">سایزی ثبت نشده</span>@endforelse</div></fieldset>
            <fieldset class="col-span-2 lg:col-span-1"><legend class="mb-2 text-sm font-semibold">رنگ</legend><div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto">@forelse($facetColors as $color)<label class="cursor-pointer rounded-full border px-3 py-1.5 text-sm has-[:checked]:border-pink-400 has-[:checked]:bg-pink-50"><input class="sr-only" type="checkbox" name="color[]" value="{{ $color }}" @checked(in_array($color, $selectedColors, true))>{{ $color }}</label>@empty<span class="text-sm text-slate-500">رنگی ثبت نشده</span>@endforelse</div></fieldset>
            <div class="col-span-2 grid grid-cols-2 gap-2 lg:col-span-1"><label class="text-sm font-semibold">حداقل قیمت<input name="min_price" type="number" min="0" value="{{ request('min_price') }}" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"></label><label class="text-sm font-semibold">حداکثر قیمت<input name="max_price" type="number" min="0" value="{{ request('max_price') }}" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"></label></div>
            <label class="col-span-2 flex items-center gap-2 text-sm lg:col-span-1"><input type="checkbox" name="available" value="1" @checked(request('available'))>فقط کالاهای موجود</label>
            <button class="col-span-2 rounded-xl bg-[var(--color-action)] px-4 py-3 font-bold text-white lg:col-span-1">اعمال فیلترها</button>
        </form>
        <div class="min-w-0 flex-1">
            @if($activeFilterLinks)<div class="mb-4 flex flex-wrap items-center gap-2" aria-label="فیلترهای فعال">@foreach($activeFilterLinks as $activeFilter)<a class="inline-flex items-center gap-1 rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-pink-700" href="{{ $activeFilter['url'] }}">{{ $activeFilter['label'] }} <x-icon name="close" size="13" /></a>@endforeach</div>@endif
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-[var(--color-muted)]">{{ $products->total() }} محصول پیدا شد</p><label class="text-sm">نمایش<select form="filters" name="sort" data-tom-select class="ms-2 rounded-lg border border-[var(--color-border)] bg-white p-2"><option value="relevant" @selected(request('sort', 'relevant') === 'relevant')>مرتبط‌ترین</option><option value="newest" @selected(request('sort')==='newest')>جدیدترین</option><option value="price_asc" @selected(request('sort')==='price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(request('sort')==='price_desc')>گران‌ترین</option></select></label></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 md:gap-5">@forelse($products as $product)<x-product-card :product="$product"/>@empty<p class="col-span-full rounded-xl bg-white p-8 text-center">با این فیلترها لباسی پیدا نکردیم.</p>@endforelse</div>
            <div class="mt-8">{{ $products->links() }}</div>
            @if(isset($category))
                @if($category->buying_guide)<section class="mt-10 rounded-2xl bg-white p-5 leading-8"><h2 class="mb-3 text-xl font-bold">راهنمای خرید {{ $category->name }}</h2><div>{{ $category->buying_guide }}</div></section>@endif
                @if(isset($sizeGuide) && $sizeGuide)<section class="mt-6 rounded-2xl bg-white p-5"><h2 class="mb-3 text-xl font-bold">{{ $sizeGuide->title }}</h2>@if($sizeGuide->instructions)<p class="mb-4 leading-8">{{ $sizeGuide->instructions }}</p>@endif<div class="overflow-x-auto"><table class="w-full min-w-[420px] text-right text-sm"><thead><tr>@foreach(array_keys($sizeGuide->rows[0] ?? []) as $heading)<th class="border-b p-2">{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach($sizeGuide->rows as $row)<tr>@foreach($row as $value)<td class="border-b p-2">{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></div>@if($sizeGuide->notes)<p class="mt-3 text-sm text-slate-600">{{ $sizeGuide->notes }}</p>@endif</section>@endif
                @if($category->faqs)<section class="mt-6 rounded-2xl bg-white p-5"><h2 class="mb-3 text-xl font-bold">پرسش‌های متداول</h2>@foreach($category->faqs as $faq)<details class="border-b py-3"><summary class="cursor-pointer font-bold">{{ $faq['question'] ?? '' }}</summary><p class="mt-2 leading-7">{{ $faq['answer'] ?? '' }}</p></details>@endforeach</section>@endif
                @if(isset($relatedCategories) && $relatedCategories->isNotEmpty())<nav class="mt-6 rounded-2xl bg-white p-5" aria-label="دسته‌های مرتبط"><h2 class="mb-3 text-xl font-bold">دسته‌های مرتبط</h2><div class="flex flex-wrap gap-2">@foreach($relatedCategories as $related)<a class="rounded-full bg-pink-50 px-4 py-2 font-bold" href="{{ route('categories.show', $related) }}">{{ $related->name }}</a>@endforeach</div></nav>@endif
            @endif
        </div>
    </div>
</section>
@endsection
