@extends('layouts.store')
@section('title', 'لباس‌های کودک | نینی‌استوری')
@section('content')
<section class="mx-auto max-w-[1500px] px-4 py-6 md:px-6">
    @if(isset($category))
        <nav class="mb-4 text-sm text-[var(--color-muted)]"><a href="{{ route('home') }}">خانه</a> / {{ $category->name }}</nav>
        @if($category->seo_content)<div class="mb-5 rounded-2xl bg-white p-5 leading-8 text-slate-700">{{ $category->seo_content }}</div>@endif
        @if($category->children->isNotEmpty())<nav class="mb-5 flex flex-wrap gap-2" aria-label="زیر‌دسته‌ها">@foreach($category->children as $child)<a class="rounded-full bg-white px-4 py-2 text-sm font-bold" href="{{ route('categories.show', $child) }}">{{ $child->name }}</a>@endforeach</nav>@endif
    @endif
    <div class="mb-6 rounded-2xl bg-[#fbe5e7] px-6 py-7 text-right md:px-10 md:py-8">
        <p class="text-sm text-[var(--color-muted)]">خانه　›　پوشاک کودک</p>
        <h1 class="mt-3 text-2xl font-extrabold md:text-3xl">لباس‌های کودک</h1>
        <p class="mt-2">انتخاب‌های لطیف برای قصه‌های هر روز</p>
    </div>
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4" aria-label="دسته‌بندی‌های تصویری">
        @foreach ($categories as $category)
            <a class="relative h-24 overflow-hidden rounded-2xl bg-[#fce9ed]" href="{{ route('categories.show', $category) }}">
                <img class="h-full w-full object-cover" src="{{ $categoryImages[$category->slug] ?? asset('images/ninistory/banner-collection.webp') }}" alt="{{ $category->name }}" loading="lazy">
                <span class="absolute bottom-2 start-2 rounded-lg bg-white/85 px-3 py-1 text-sm font-bold">{{ $category->name }}</span>
            </a>
        @endforeach
    </div>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <form id="filters" class="grid grid-cols-2 gap-3 rounded-2xl border border-[var(--color-border)] bg-white p-4 shadow-sm lg:sticky lg:top-44 lg:w-[260px] lg:shrink-0 lg:grid-cols-1 lg:gap-5 lg:p-5">
            <h2 class="col-span-2 text-lg font-bold lg:col-span-1">فیلتر محصولات</h2>
            <label class="text-sm font-semibold">جست‌وجو<input name="q" value="{{ request('q') }}" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"></label>
            <label class="text-sm font-semibold">دسته‌بندی<select name="category" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"><option value="">همه دسته‌ها</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">مرتب‌سازی<select name="sort" class="mt-2 block w-full rounded-lg border border-[var(--color-border)] p-2.5 font-normal"><option value="newest">جدیدترین</option><option value="price_asc" @selected(request('sort')==='price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(request('sort')==='price_desc')>گران‌ترین</option></select></label>
            <label class="col-span-2 flex items-center gap-2 text-sm lg:col-span-1"><input type="checkbox" name="available" value="1" @checked(request('available'))>فقط کالاهای موجود</label>
            <button class="col-span-2 rounded-xl bg-[var(--color-action)] px-4 py-3 font-bold text-white lg:col-span-1">اعمال فیلترها</button>
        </form>
        <div class="min-w-0 flex-1">
            <div class="mb-4 flex items-center justify-between"><p class="text-sm text-[var(--color-muted)]">{{ $products->total() }} محصول</p><label class="text-sm">نمایش<select form="filters" name="sort" class="ms-2 rounded-lg border border-[var(--color-border)] bg-white p-2"><option value="newest">جدیدترین</option><option value="price_asc" @selected(request('sort')==='price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(request('sort')==='price_desc')>گران‌ترین</option></select></label></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 md:gap-5">@forelse($products as $product)<x-product-card :product="$product"/>@empty<p class="col-span-full rounded-xl bg-white p-8 text-center">با این فیلترها لباسی پیدا نکردیم.</p>@endforelse</div>
            <div class="mt-8">{{ $products->links() }}</div>
        </div>
    </div>
</section>
@endsection
