@extends('layouts.store')

@section('title', 'کیدوکی | قصه‌ی خوش‌پوشی')

@section('content')
    @php
        $hero = $heroSlides->first();
        $secondary = $banners['secondary'];
        $collection = $banners['collection'];
    @endphp
    @push('head')
        <link rel="preload" as="image" href="{{ $hero['mobile_url'] }}" media="(max-width: 767px)" fetchpriority="high">
        <link rel="preload" as="image" href="{{ $hero['desktop_url'] }}" media="(min-width: 768px)" fetchpriority="high">
    @endpush

    <div class="home-shell">
        <section class="campaign-slider" data-campaign-slider aria-roledescription="carousel" aria-label="کمپین‌های کیدوکی">
            <div class="campaign-slider__track">
                @foreach ($heroSlides as $slide)
                    <article class="campaign-slide campaign-slide--{{ $slide['text_position'] ?? 'right' }} campaign-slide--overlay-{{ $slide['overlay_style'] ?? 'soft' }} {{ $loop->first ? 'is-active' : '' }}" data-campaign-slide data-duration="{{ $slide['duration_seconds'] ?? 6 }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} از {{ $heroSlides->count() }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                        <picture class="campaign-slide__media">
                            <source media="(max-width: 767px)" srcset="{{ $slide['mobile_url'] }}">
                            <img src="{{ $slide['desktop_url'] }}" alt="{{ $slide['alt_text'] }}" width="1600" height="700" sizes="100vw" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}">
                        </picture>
                        <div class="campaign-slide__wash" aria-hidden="true"></div>
                        <div class="campaign-slide__copy">
                            <p class="eyebrow">{{ $slide['subtitle'] }}</p>
                            <h1>{{ $slide['title'] }}</h1>
                            @if ($slide['cta_label'] && $slide['cta_url'])
                                <a class="button button--pink" href="{{ $slide['cta_url'] }}">{{ $slide['cta_label'] }} <x-icon name="arrow-left" size="16" /></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($heroSlides->count() > 1)
                <div class="campaign-slider__controls">
                    <button type="button" class="campaign-slider__arrow" data-campaign-prev aria-label="کمپین قبلی"><x-icon name="arrow-right" size="18" /></button>
                    <div class="campaign-slider__dots" role="tablist" aria-label="انتخاب کمپین">
                        @foreach ($heroSlides as $slide)
                            <button type="button" class="campaign-slider__dot {{ $loop->first ? 'is-active' : '' }}" data-campaign-dot="{{ $loop->index }}" role="tab" aria-label="نمایش کمپین {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"></button>
                        @endforeach
                    </div>
                    <button type="button" class="campaign-slider__arrow" data-campaign-next aria-label="کمپین بعدی"><x-icon name="arrow-left" size="18" /></button>
                </div>
            @endif
        </section>
        @if (app()->environment(['local', 'testing']) && $heroSlides->count() < 2)
            <p class="campaign-diagnostic" data-campaign-diagnostic>تشخیص توسعه: {{ $heroSlides->count() }} اسلاید فعال و زمان‌بندی‌شده برای کمپین اصلی پیدا شد؛ برای تست چرخش، یک بنر Hero دوم از مدیریت بنرها اضافه کنید.</p>
        @endif

        <section class="home-categories" aria-label="دسته‌های منتخب کیدوکی">
            @foreach ($homepageCategorySlots as $slot)
                @php($title = $slot['title'])
                <a class="category-tile" data-home-category-card href="{{ $slot['url'] }}" aria-label="{{ $title }}">
                    <img src="{{ $slot['image_url'] }}" alt="{{ $title }}" width="800" height="600" loading="lazy" decoding="async">
                    <span>{{ $title }}</span>
                            <x-icon name="arrow-left" size="17" class="category-tile__arrow" />
                </a>
            @endforeach
        </section>

        <section class="home-service-row" aria-label="امکانات خرید کیدوکی">
            <div><x-icon name="search" size="17" /> جست‌وجوی سریع لباس‌ها</div>
            <div><span aria-hidden="true">◌</span> انتخاب دقیق رنگ و سایز</div>
            <div><span aria-hidden="true">▣</span> پرداخت از مسیر امن فروشگاه</div>
            <div><x-icon name="user" size="17" /> پیگیری سفارش از حساب کاربری</div>
        </section>

        <section class="home-products" aria-labelledby="bestsellers-title">
                <div class="section-heading">
                <div><p class="eyebrow">انتخاب‌های واقعی کیدوکی</p><h2 id="bestsellers-title">محصولات منتخب <x-icon name="sparkle" size="18" /></h2></div>
                <a href="{{ route('products.index') }}">مشاهده‌ی همه <x-icon name="arrow-left" size="15" /></a>
            </div>
            <div class="product-grid">
                @forelse ($featured as $product)
                    <x-product-card :product="$product" />
                @empty
                    <p class="empty-products">به‌زودی محصولات تازه به فروشگاه اضافه می‌شوند.</p>
                @endforelse
            </div>
        </section>

        @if($newArrivals->isNotEmpty())
            <section class="home-products" aria-labelledby="new-arrivals-title">
                <div class="section-heading"><div><p class="eyebrow">تازه رسیده‌ها</p><h2 id="new-arrivals-title">جدیدترین لباس‌ها</h2></div><a href="{{ route('products.index', ['sort' => 'newest']) }}">همه جدیدها <x-icon name="arrow-left" size="15" /></a></div>
                <div class="product-grid">@foreach($newArrivals as $product)<x-product-card :product="$product" />@endforeach</div>
            </section>
        @endif

        @if($offers->isNotEmpty())
            <section class="home-products" aria-labelledby="offers-title">
                <div class="section-heading"><div><p class="eyebrow">قیمت‌های واقعی فروشگاه</p><h2 id="offers-title">فرصت‌های خرید</h2></div><a href="{{ route('products.index', ['sort' => 'price_asc']) }}">مشاهده همه <x-icon name="arrow-left" size="15" /></a></div>
                <div class="product-grid">@foreach($offers as $product)<x-product-card :product="$product" />@endforeach</div>
            </section>
        @endif

        <section class="home-editorial" aria-label="{{ $collection['title'] }}">
            <a href="{{ $collection['cta_url'] }}" class="home-editorial__link">
                <picture>
                    <source media="(max-width: 767px)" srcset="{{ $collection['mobile_url'] }}">
                    <img src="{{ $collection['desktop_url'] }}" alt="{{ $collection['alt_text'] }}" loading="lazy">
                </picture>
                <span class="home-editorial__copy">
                    <span class="eyebrow">{{ $collection['subtitle'] }}</span>
                    <strong>{{ $collection['title'] }}</strong>
                    @if ($collection['cta_label'])<span class="button button--pink">{{ $collection['cta_label'] }} <x-icon name="arrow-left" size="15" /></span>@endif
                </span>
            </a>
        </section>

        <section class="home-discovery-grid" aria-label="پیشنهادهای خرید">
            <a class="home-discovery-card home-discovery-card--image" href="{{ $secondary['cta_url'] }}">
                <img src="{{ $secondary['desktop_url'] }}" alt="{{ $secondary['alt_text'] }}" loading="lazy">
                <span class="home-discovery-card__copy">
                    <span class="eyebrow">{{ $secondary['subtitle'] }}</span>
                    <strong>{{ $secondary['title'] }}</strong>
                    @if ($secondary['cta_label'])<span>{{ $secondary['cta_label'] }} <x-icon name="arrow-left" size="15" /></span>@endif
                </span>
            </a>
            <div class="home-discovery-card home-discovery-card--size" id="size-shortcuts">
                <span class="home-discovery-card__copy">
                    <span class="eyebrow">خرید بر اساس سایز</span>
                    <strong>سایز مناسب را سریع پیدا کنید</strong>
                    <span class="size-shortcuts" aria-label="انتخاب سایز">
                        @forelse($sizeShortcuts as $size)
                            <a href="{{ route('products.index', ['size' => [$size]]) }}">{{ $size }}</a>
                        @empty
                            <a href="{{ route('products.index') }}">مشاهده همه لباس‌ها</a>
                        @endforelse
                    </span>
                </span>
            </div>
        </section>
    </div>
@endsection
