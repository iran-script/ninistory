<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @php($seo = $seo ?? app(\App\Services\SeoMetadataService::class)->defaults())
    @php($seoSettings = app(\App\Services\SeoMetadataService::class)->settings())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <meta property="og:type" content="{{ request()->routeIs('products.show') ? 'product' : 'website' }}">
    <meta property="og:title" content="{{ $seo['og_title'] }}">
    <meta property="og:description" content="{{ $seo['og_description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:site_name" content="Kidoki | کیدوکی">
    @if($seo['og_image'])<meta property="og:image" content="{{ $seo['og_image'] }}">@endif
    @if(app()->environment('production') && $seoSettings?->search_console_verification)<meta name="google-site-verification" content="{{ $seoSettings->search_console_verification }}">@endif
    @foreach(($schemas ?? []) as $schema)<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="storefront-shell min-h-screen font-sans antialiased">
    <div class="announcement-bar"><span>به خانواده‌ی کیدوکی خوش آمدید <x-icon name="heart" size="14" class="announcement-heart" /></span><span>انتخاب رنگ و سایز <i>•</i> مشاهده موجودی <i>•</i> پرداخت از مسیر امن فروشگاه</span></div>
    <header class="site-header">
        <div class="desktop-header">
            <div class="header-actions">
                <a href="{{ auth()->check() ? route('orders.index') : route('products.index') }}"><x-icon name="user" />{{ auth()->check() ? 'حساب من' : 'ورود / ثبت‌نام' }}</a>
                <a href="{{ route('products.index') }}"><x-icon name="heart" />علاقه‌مندی‌ها</a>
                <a class="cart-link" href="{{ route('cart') }}"><x-icon name="bag" />سبد خرید <b data-cart-count>{{ count(session('cart', [])) }}</b></a>
            </div>
            <form action="{{ route('products.index') }}" class="header-search" data-live-search>
                <label class="sr-only" for="desktop-search">جست‌وجوی لباس</label>
                <input id="desktop-search" name="q" value="{{ request('q') }}" placeholder="جست‌وجوی لباس، دسته‌بندی یا برند...">
                <button type="submit" aria-label="جست‌وجو"><x-icon name="search" size="20" /></button>
            </form>
            <a href="{{ route('home') }}" class="brand" aria-label="کیدوکی، صفحه‌ی اصلی">
                <img src="{{ asset('brand-logo.webp') }}" alt="نشان کیدوکی" width="190" height="190" decoding="async">
            </a>
        </div>
        <div class="mobile-header">
            <button type="button" class="icon-button" aria-label="باز کردن منوی دسته‌ها" data-mobile-menu-open aria-expanded="false"><x-icon name="menu" size="23" /></button>
            <a href="{{ route('home') }}" class="brand brand--mobile"><img src="{{ asset('brand-logo.webp') }}" alt="نشان کیدوکی" width="190" height="190" decoding="async"></a>
            <div class="mobile-header__actions"><a class="icon-button" href="{{ auth()->check() ? route('orders.index') : route('products.index') }}" aria-label="حساب کاربری"><x-icon name="user" size="21" /></a><a class="icon-button" href="{{ route('cart') }}" aria-label="سبد خرید"><x-icon name="bag" size="21" /><b class="mobile-cart-count" data-cart-count>{{ count(session('cart', [])) }}</b></a></div>
        </div>
        <form action="{{ route('products.index') }}" class="mobile-search" data-live-search>
            <label class="sr-only" for="mobile-search">جست‌وجوی لباس</label>
            <input id="mobile-search" name="q" type="search" inputmode="search" value="{{ request('q') }}" placeholder="دنبال چه لباسی می‌گردی؟">
            <button type="submit" aria-label="جست‌وجو"><x-icon name="search" size="20" /></button>
        </form>
        <nav class="main-nav" aria-label="دسته‌بندی‌ها">
            <a class="main-nav__active" href="{{ route('home') }}"><x-icon name="home" size="16" /> خانه</a>
            <div class="mega-nav-item" data-mega-item>
                <button id="desktop-category-trigger" type="button" data-mega-trigger aria-expanded="false" aria-controls="mega-menu-categories">دسته‌بندی‌ها <x-icon name="chevron-left" size="15" /></button>
                <div class="mega-menu" id="mega-menu-categories" hidden data-mega-menu>
                    <div class="mega-menu__shell" data-desktop-navigation data-navigation-tree='@json($navigationCategories)'>
                        <aside class="mega-menu__roots" aria-label="دسته‌های اصلی"></aside>
                        <section class="mega-menu__children" aria-live="polite" aria-label="زیر دسته‌ها"></section>
                        <aside class="mega-menu__feature" aria-label="نمایش دسته"></aside>
                    </div>
                </div>
            </div>
            <a href="{{ route('products.index') }}">انواع لباس</a>
            <a href="{{ route('products.index', ['sort' => 'newest']) }}">جدیدترین‌ها</a>
        </nav>
    </header>

    <div class="mobile-category-drawer" data-mobile-menu aria-hidden="true">
        <button type="button" class="mobile-category-drawer__backdrop" data-mobile-menu-close aria-label="بستن منوی دسته‌ها"></button>
        <aside class="mobile-category-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="mobile-menu-title" tabindex="-1">
            <div class="flex items-center justify-between border-b border-[#efded7] px-5 py-4"><button type="button" class="hidden rounded-xl border px-3 py-2 text-sm" data-mobile-menu-back>بازگشت</button><h2 id="mobile-menu-title" class="text-lg font-extrabold" data-mobile-menu-title>دسته‌بندی‌ها</h2><button type="button" class="rounded-full px-3 py-2 text-xl" data-mobile-menu-close aria-label="بستن"><x-icon name="close" size="20" /></button></div>
            <nav class="mobile-category-drawer__content" aria-label="دسته‌بندی موبایل" data-mobile-menu-content data-mobile-navigation data-navigation-tree='@json($navigationCategories)'></nav>
        </aside>
    </div>

    <main class="pb-20 md:pb-0">@yield('content')</main>

    <nav class="mobile-bottom-nav" aria-label="ناوبری پایین صفحه">
        <a class="mobile-bottom-nav__active" href="{{ route('home') }}"><x-icon name="home" size="19" />خانه</a>
        <button type="button" data-mobile-menu-open aria-label="باز کردن دسته‌بندی‌ها"><x-icon name="grid" size="19" />دسته‌بندی</button>
        <a href="{{ route('cart') }}"><x-icon name="bag" size="19" />سبد خرید</a>
        <a href="{{ route('home') }}#club"><x-icon name="sparkle" size="19" />باشگاه</a>
        <a href="{{ route('products.index') }}"><x-icon name="user" size="19" />پروفایل</a>
    </nav>

    <div class="cart-drawer hidden" data-cart-drawer aria-hidden="true">
        <button type="button" class="cart-drawer__backdrop" data-cart-close aria-label="بستن سبد خرید"></button>
        <aside class="cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" tabindex="-1">
            <div class="flex items-center justify-between gap-3 border-b border-[#efded7] px-5 py-4">
                <div><p class="text-xs text-[var(--color-muted)]" data-cart-status>افزوده شد</p><h2 id="cart-drawer-title" class="text-xl font-extrabold">سبد خرید شما</h2></div>
                <button type="button" class="rounded-full px-3 py-2 text-xl" data-cart-close aria-label="بستن سبد خرید"><x-icon name="close" size="20" /></button>
            </div>
            <div class="flex items-center gap-3 px-5 py-5" data-cart-item></div>
            <div class="mt-auto border-t border-[#efded7] px-5 py-4">
                <div class="mb-4 flex items-center justify-between font-bold"><span>جمع کالاها</span><span data-cart-total>۰ تومان</span></div>
                <div class="grid grid-cols-2 gap-2"><button type="button" class="rounded-xl border border-[#e6cbd2] px-4 py-3 font-bold" data-cart-close>ادامه خرید</button><a class="rounded-xl bg-[var(--color-action)] px-4 py-3 text-center font-bold text-white" href="{{ route('cart') }}">مشاهده سبد خرید</a></div>
            </div>
        </aside>
    </div>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div><img src="{{ asset('images/brand/logo-512.webp') }}" alt="نشان کیدوکی" width="96" height="96" loading="lazy" decoding="async"><a href="{{ route('home') }}" class="footer-brand">کیدوکی | Kidoki</a><p>قصه‌ی خوش‌پوشی کوچولوها؛ لباس‌های لطیف و دوست‌داشتنی برای روزهای کودکی.</p><nav class="mt-3 flex flex-wrap gap-3 text-sm">@foreach(['about'=>'درباره کیدوکی','contact'=>'تماس با ما','buying-guide'=>'راهنمای خرید','shipping'=>'ارسال','returns'=>'مرجوعی','privacy'=>'حریم خصوصی','faq'=>'پرسش‌های متداول'] as $slug=>$label)@if(isset($publishedContentPages[$slug]))<a href="{{ route('content-pages.show',$slug) }}">{{ $label }}</a>@endif @endforeach</nav></div>
            <div><strong>راهنمای خرید</strong><a href="{{ route('products.index') }}">همه‌ی محصولات</a><a href="{{ route('articles.index') }}">مجله و راهنماها</a><a href="{{ route('cart') }}">سبد خرید</a></div>
            <div><strong>پشتیبانی</strong><span>هر روز، ۹ تا ۱۸</span><span>ارسال به سراسر ایران</span></div>
        </div>
        <div class="site-footer__copyright">© کیدوکی | Kidoki</div>
    </footer>
    @stack('scripts')
</body>
</html>
