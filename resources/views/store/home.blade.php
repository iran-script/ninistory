@extends('layouts.store')

@section('title', 'نینی‌استوری | قصه‌ی خوش‌پوشی')

@section('content')
    @php
        $presentation = [
            'girls' => ['title' => 'دخترانه', 'image' => 'girls'],
            'demo-girls' => ['title' => 'دخترانه', 'image' => 'girls'],
            'boys' => ['title' => 'پسرانه', 'image' => 'boys'],
            'demo-boys' => ['title' => 'پسرانه', 'image' => 'boys'],
            'baby' => ['title' => 'نوزادی', 'image' => 'newborn'],
            'demo-baby' => ['title' => 'نوزادی', 'image' => 'newborn'],
            'newborn' => ['title' => 'نوزادی', 'image' => 'newborn'],
        ];
        $hero = $banners['hero'];
        $secondary = $banners['secondary'];
        $collection = $banners['collection'];
    @endphp

    <div class="home-shell">
        <section class="home-hero" aria-label="{{ $hero['title'] }}">
            <picture>
                <source media="(max-width: 767px)" srcset="{{ $hero['mobile_url'] }}">
                <img class="home-hero__image" src="{{ $hero['desktop_url'] }}" alt="{{ $hero['alt_text'] }}" fetchpriority="high">
            </picture>
            <div class="home-hero__copy">
                <p class="eyebrow">{{ $hero['subtitle'] }}</p>
                <h1>{{ $hero['title'] }}</h1>
                @if ($hero['cta_label'] && $hero['cta_url'])
                    <a class="button button--pink" href="{{ $hero['cta_url'] }}">{{ $hero['cta_label'] }} <span aria-hidden="true">←</span></a>
                @endif
            </div>
        </section>

        <section class="home-categories" aria-label="دسته‌بندی محصولات">
            @foreach ($categories as $category)
                @php($categoryPresentation = $presentation[$category->slug] ?? ['title' => $category->name, 'image' => 'newborn'])
                <a class="category-tile category-tile--{{ $categoryPresentation['image'] }}" href="{{ route('categories.show', $category) }}" aria-label="{{ $categoryPresentation['title'] }}">
                    <img src="{{ $categoryImages[$category->slug] ?? asset('images/ninistory/banner-collection.webp') }}" alt="{{ $categoryPresentation['title'] }}" loading="lazy">
                    <span>{{ $categoryPresentation['title'] }}</span>
                    <span class="category-tile__arrow" aria-hidden="true">←</span>
                </a>
            @endforeach
            <a class="category-tile category-tile--season" href="{{ route('products.index') }}" aria-label="مجموعه‌های فصلی">
                <img src="{{ $collection['desktop_url'] }}" alt="مجموعه‌های فصلی" loading="lazy">
                <span>{{ $collection['title'] }}</span>
                <span class="category-tile__arrow" aria-hidden="true">←</span>
            </a>
        </section>

        <section class="service-strip" aria-label="خدمات نینی‌استوری">
            <div><span aria-hidden="true">□</span> ارسال سریع به سراسر ایران</div>
            <div><span aria-hidden="true">♢</span> ضمانت اصالت کالا</div>
            <div><span aria-hidden="true">↶</span> ۷ روز امکان بازگشت</div>
            <div><span aria-hidden="true">♧</span> پشتیبانی دوستانه</div>
        </section>

        <section class="home-products" aria-labelledby="bestsellers-title">
            <div class="section-heading">
                <div><p class="eyebrow">انتخاب‌های محبوب</p><h2 id="bestsellers-title">پرفروش‌ها <span aria-hidden="true">✦</span></h2></div>
                <a href="{{ route('products.index') }}">مشاهده‌ی همه ←</a>
            </div>
            <div class="product-grid">
                @forelse ($featured as $product)
                    <x-product-card :product="$product" />
                @empty
                    <p class="empty-products">به‌زودی محصولات تازه به فروشگاه اضافه می‌شوند.</p>
                @endforelse
            </div>
        </section>

        <section class="seasonal-promo" aria-label="{{ $collection['title'] }}">
            <a href="{{ $collection['cta_url'] }}" class="seasonal-promo__link">
                <picture>
                    <source media="(max-width: 767px)" srcset="{{ $collection['mobile_url'] }}">
                    <img src="{{ $collection['desktop_url'] }}" alt="{{ $collection['alt_text'] }}" loading="lazy">
                </picture>
                <span class="seasonal-promo__copy">
                    <span class="eyebrow">{{ $collection['subtitle'] }}</span>
                    <strong>{{ $collection['title'] }}</strong>
                    @if ($collection['cta_label'])<span class="button button--pink">{{ $collection['cta_label'] }} ←</span>@endif
                </span>
            </a>
        </section>

        <section class="home-promos" aria-label="پیشنهادهای ویژه">
            <a class="promo-card promo-card--club" href="{{ $secondary['cta_url'] }}">
                <img src="{{ $secondary['desktop_url'] }}" alt="{{ $secondary['alt_text'] }}" loading="lazy">
                <span class="promo-card__copy">
                    <span class="eyebrow">{{ $secondary['subtitle'] }}</span>
                    <strong>{{ $secondary['title'] }}</strong>
                    @if ($secondary['cta_label'])<span>{{ $secondary['cta_label'] }} ←</span>@endif
                </span>
            </a>
            <div class="promo-card promo-card--size">
                <img src="{{ $demoMediaUrl }}" alt="نمونه‌ی نمایشی طراحی نینی‌استوری؛ غیرقابل خرید" loading="lazy">
                <span class="promo-card__copy">
                    <span class="eyebrow">نمونه‌ی نمایشی</span>
                    <strong>راهنمای اندازه و قصه‌های کوچک</strong>
                    <span>این تصویر نمونه است و به کالای قابل خرید یا SKU واقعی متصل نیست.</span>
                </span>
            </div>
        </section>
    </div>
@endsection
