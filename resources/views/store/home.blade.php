@extends('layouts.store')

@section('title', 'نینی‌استوری | قصه‌ی خوش‌پوشی')

@section('content')
@php
    $previewOnly = app()->environment(['local', 'testing']);
    $categoryPresentation = [
        'girls' => ['title' => 'دخترانه', 'image' => 'girls'],
        'boys' => ['title' => 'پسرانه', 'image' => 'boys'],
        'baby' => ['title' => 'نوزادی', 'image' => 'newborn'],
        'newborn' => ['title' => 'نوزادی', 'image' => 'newborn'],
    ];
@endphp

<div class="home-shell">
    <section class="home-hero" aria-label="مجموعه‌ی پاییز و زمستان نینی‌استوری">
        @if($previewOnly)
            <img class="home-hero__image" src="{{ route('preview.asset', ['path' => 'images/banners/hero-desktop.jpg']) }}" alt="تصویر نمایشی مجموعه‌ی پاییز و زمستان نینی‌استوری" fetchpriority="high">
            <span class="preview-label">تصویر نمایشی</span>
        @else
            <div class="home-hero__production-art" aria-hidden="true"></div>
            <div class="home-hero__copy">
                <p class="eyebrow">قصه‌های قشنگ برای کوچولوها</p>
                <h1>روزهای شیرین<br>با لباس‌های دوست‌داشتنی</h1>
                <a class="button button--pink" href="{{ route('products.index') }}">دیدن مجموعه <span aria-hidden="true">←</span></a>
            </div>
        @endif
    </section>

    <section class="home-categories" aria-label="دسته‌بندی محصولات">
        @foreach($categories as $category)
            @php($presentation = $categoryPresentation[$category->slug] ?? ['title' => $category->name, 'image' => 'newborn'])
            <a class="category-tile category-tile--{{ $presentation['image'] }}" aria-label="{{ $presentation['title'] }}" href="{{ route('products.index', ['category' => $category->id]) }}">
                @if($previewOnly)
                    <img src="{{ route('preview.asset', ['path' => 'images/categories/'.$presentation['image'].'.jpg']) }}" alt="" loading="lazy">
                @else
                    <span>{{ $presentation['title'] }}</span>
                @endif
                <span class="category-tile__arrow" aria-hidden="true">←</span>
            </a>
        @endforeach
        <a class="category-tile category-tile--season" aria-label="?????????? ????" href="{{ route('products.index') }}">
            @if($previewOnly)<img src="{{ route('preview.asset', ['path' => 'images/banners/autumn-collection.jpg']) }}" alt="" loading="lazy">@endif
            @unless($previewOnly)<span>مجموعه‌های فصلی</span>@endunless<span class="category-tile__arrow" aria-hidden="true">←</span>
        </a>
    </section>

    <section class="service-strip" aria-label="خدمات نینی‌استوری">
        <div><span aria-hidden="true">▱</span> ارسال سریع به سراسر ایران</div>
        <div><span aria-hidden="true">♢</span> ضمانت اصالت کالا</div>
        <div><span aria-hidden="true">↶</span> ۷ روز امکان بازگشت</div>
        <div><span aria-hidden="true">♡</span> پشتیبانی دوستانه</div>
    </section>

    <section class="home-products" aria-labelledby="bestsellers-title">
        <div class="section-heading">
            <div><p class="eyebrow">انتخاب‌های محبوب</p><h2 id="bestsellers-title">پرفروش‌ها <span aria-hidden="true">☆</span></h2></div>
            <a href="{{ route('products.index') }}">مشاهده‌ی همه <span aria-hidden="true">←</span></a>
        </div>
        <div class="product-grid">
            @forelse($featured as $product)
                <x-product-card :product="$product" />
            @empty
                <p class="empty-products">به‌زودی محصولات تازه به فروشگاه اضافه می‌شوند.</p>
            @endforelse
        </div>
    </section>

    <section class="seasonal-promo" aria-label="کالکشن‌های داستانی">
        <a href="{{ route('products.index') }}" class="seasonal-promo__link">
            @if($previewOnly)
                <img src="{{ route('preview.asset', ['path' => 'images/banners/autumn-collection.jpg']) }}" alt="تصویر نمایشی کالکشن فصلی" loading="lazy">
                <span class="preview-label">تصویر نمایشی</span>
            @else
                <span class="seasonal-promo__copy"><span class="eyebrow">قصه‌ی یک فصل تازه</span><strong>کالکشن‌های داستانی</strong><span>لباس‌هایی با الهام از طبیعت و خیال‌انگیزترین روزها</span><span class="button button--pink">دیدن کالکشن <span aria-hidden="true">←</span></span></span>
            @endif
        </a>
    </section>

    <section class="home-promos" aria-label="خدمات ویژه">
        <a class="promo-card promo-card--club" href="{{ route('home') }}#club">
            @if($previewOnly)<img src="{{ route('preview.asset', ['path' => 'images/banners/club.jpg']) }}" alt="تصویر نمایشی باشگاه نینی‌استوری" loading="lazy"><span class="preview-label">تصویر نمایشی</span>@else<span class="promo-card__icon" aria-hidden="true">🎁</span>@endif
            @unless($previewOnly)<span class="promo-card__copy"><span class="eyebrow">NiniStory Club</span><strong>باشگاه نینی‌استوری</strong><span>با هر خرید امتیاز بگیر و هدیه‌های ویژه دریافت کن</span></span>@endunless
        </a>
        <a class="promo-card promo-card--size" href="{{ route('products.index') }}">
            @if($previewOnly)<img src="{{ route('preview.asset', ['path' => 'images/banners/size-guide.jpg']) }}" alt="تصویر نمایشی راهنمای اندازه" loading="lazy"><span class="preview-label">تصویر نمایشی</span>@else<span class="promo-card__icon" aria-hidden="true">♧</span>@endif
            @unless($previewOnly)<span class="promo-card__copy"><strong>راهنمای اندازه</strong><span>انتخاب سایز مناسب با جدول کامل اندازه‌ها</span></span>@endunless
        </a>
    </section>
</div>
@endsection
