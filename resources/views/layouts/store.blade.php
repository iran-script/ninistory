<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نینی‌استوری | قصه‌ی خوش‌پوشی')</title>
    <meta name="description" content="لباس‌های لطیف و دوست‌داشتنی برای کوچولوها؛ انتخابی شیرین برای روزهای کودکی.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    <div class="announcement-bar"><span>به خانواده‌ی نینی‌استوری خوش آمدید <span class="announcement-heart" aria-hidden="true">♥</span></span><span>ارسال به سراسر ایران <i>•</i> ضمانت اصالت کالا <i>•</i> ۷ روز امکان بازگشت</span></div>
    <header class="site-header">
        <div class="desktop-header">
            <div class="header-actions">
                <a href="{{ route('products.index') }}">ورود / ثبت‌نام <span aria-hidden="true">♙</span></a>
                <a href="{{ route('products.index') }}">علاقه‌مندی‌ها <span aria-hidden="true">♡</span></a>
                <a class="cart-link" href="{{ route('cart') }}">سبد خرید <span aria-hidden="true">♧</span><b>{{ count(session('cart', [])) }}</b></a>
            </div>
            <form action="{{ route('products.index') }}" class="header-search">
                <label class="sr-only" for="desktop-search">جست‌وجوی لباس</label>
                <input id="desktop-search" name="q" value="{{ request('q') }}" placeholder="جست‌وجوی لباس، دسته‌بندی یا برند...">
                <button type="submit" aria-label="جست‌وجو">⌕</button>
            </form>
            <a href="{{ route('home') }}" class="brand" aria-label="نینی‌استوری، صفحه‌ی اصلی">
                <img src="{{ asset('brand-logo.png') }}" alt="نینی‌استوری">
            </a>
        </div>
        <div class="mobile-header">
            <button type="button" class="icon-button" aria-label="جست‌وجو" onclick="document.getElementById('mobile-search').focus()"><span aria-hidden="true">⌕</span></button>
            <a href="{{ route('home') }}" class="brand brand--mobile"><img src="{{ asset('brand-logo.png') }}" alt="نینی‌استوری"></a>
            <div class="mobile-header__actions"><a href="{{ route('products.index') }}" aria-label="علاقه‌مندی‌ها">♡</a><a href="{{ route('cart') }}" aria-label="سبد خرید">♧</a></div>
        </div>
        <form action="{{ route('products.index') }}" class="mobile-search">
            <label class="sr-only" for="mobile-search">جست‌وجوی لباس</label>
            <input id="mobile-search" name="q" value="{{ request('q') }}" placeholder="دنبال چه لباسی برای کوچولوت می‌گردی؟">
            <button type="submit" aria-label="جست‌وجو">⌕</button>
        </form>
        <nav class="main-nav" aria-label="دسته‌بندی‌ها">
            <a class="main-nav__active" href="{{ route('home') }}"><span aria-hidden="true">⌂</span> خانه</a>
            <a href="{{ route('products.index', ['category' => 2]) }}">دخترانه <span aria-hidden="true">♧</span></a>
            <a href="{{ route('products.index', ['category' => 3]) }}">پسرانه <span aria-hidden="true">♧</span></a>
            <a href="{{ route('products.index', ['category' => 1]) }}">نوزادی <span aria-hidden="true">♡</span></a>
            <a href="{{ route('products.index') }}">مجموعه‌های ویژه <span aria-hidden="true">☆</span></a>
            <a href="{{ route('products.index') }}">جدیدترین‌ها <span aria-hidden="true">✦</span></a>
            <a href="{{ route('products.index') }}">تخفیف‌ها <span aria-hidden="true">♧</span></a>
            <a href="{{ route('home') }}#club">باشگاه نینی‌استوری <span aria-hidden="true">♧</span></a>
            <a href="{{ route('products.index') }}">Magic Fit <span aria-hidden="true">✧</span></a>
        </nav>
    </header>

    <main class="pb-20 md:pb-0">@yield('content')</main>

    <nav class="mobile-bottom-nav" aria-label="ناوبری پایین صفحه">
        <a class="mobile-bottom-nav__active" href="{{ route('home') }}"><span aria-hidden="true">⌂</span>خانه</a>
        <a href="{{ route('products.index') }}"><span aria-hidden="true">▦</span>دسته‌بندی</a>
        <a href="{{ route('cart') }}"><span aria-hidden="true">♧</span>سبد خرید</a>
        <a href="{{ route('home') }}#club"><span aria-hidden="true">♧</span>باشگاه</a>
        <a href="{{ route('products.index') }}"><span aria-hidden="true">♙</span>پروفایل</a>
    </nav>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div><a href="{{ route('home') }}" class="footer-brand">نینی‌استوری</a><p>قصه‌ی خوش‌پوشی کوچولوها؛ لباس‌های لطیف و دوست‌داشتنی برای روزهای کودکی.</p></div>
            <div><strong>راهنمای خرید</strong><a href="{{ route('products.index') }}">همه‌ی محصولات</a><a href="{{ route('cart') }}">سبد خرید</a></div>
            <div><strong>پشتیبانی</strong><span>هر روز، ۹ تا ۱۸</span><span>ارسال به سراسر ایران</span></div>
        </div>
        <div class="site-footer__copyright">© نینی‌استوری</div>
    </footer>
</body>
</html>
