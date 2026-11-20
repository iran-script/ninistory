<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'مدیریت فروشگاه | NiniStory')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="admin font-sans">
<div class="admin-layout">
    <aside class="admin-side" id="admin-sidebar" aria-label="ناوبری مدیریت">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand">
            <span class="admin-brand__mark" aria-hidden="true">N</span>
            <span><strong>NiniStory</strong><small>پنل مدیریت فروشگاه</small></span>
        </a>
        <p class="admin-side__caption">مدیریت فروشگاه</p>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])><span class="admin-nav__icon" aria-hidden="true">⌂</span>داشبورد</a>
            <p class="admin-nav__group">کاتالوگ</p>
            <a href="{{ route('admin.products.index') }}" @class(['is-active' => request()->routeIs('admin.products.*')])><span class="admin-nav__icon" aria-hidden="true">◇</span>محصولات</a>
            <a href="{{ route('admin.categories.index') }}" @class(['is-active' => request()->routeIs('admin.categories.*')])><span class="admin-nav__icon" aria-hidden="true">▱</span>دسته‌بندی‌ها</a>
            <a href="{{ route('admin.inventory.index') }}" @class(['is-active' => request()->routeIs('admin.inventory.*') || request()->routeIs('admin.variants.*')])><span class="admin-nav__icon" aria-hidden="true">⬡</span>موجودی و انبار</a>
            <p class="admin-nav__group">سفارش و محتوا</p>
            <a href="{{ route('admin.orders.index') }}" @class(['is-active' => request()->routeIs('admin.orders.*')])><span class="admin-nav__icon" aria-hidden="true">▣</span>سفارش‌ها</a>
            <a href="{{ route('admin.articles.index') }}" @class(['is-active' => request()->routeIs('admin.articles.*')])><span class="admin-nav__icon" aria-hidden="true">▤</span>مجله و مقالات</a>
            <a href="{{ route('admin.banners.index') }}" @class(['is-active' => request()->routeIs('admin.banners.*')])><span class="admin-nav__icon" aria-hidden="true">▧</span>بنرهای فروشگاه</a>
            <a href="{{ route('admin.homepage-categories.edit') }}" @class(['is-active' => request()->routeIs('admin.homepage-categories.*')])><span class="admin-nav__icon" aria-hidden="true">▦</span>دسته‌های صفحه اصلی</a>
            <p class="admin-nav__group">تنظیمات</p>
            <a href="{{ route('admin.payments.index') }}" @class(['is-active' => request()->routeIs('admin.payments.*')])><span class="admin-nav__icon" aria-hidden="true">◈</span>پرداخت‌ها</a>
            <a href="{{ route('admin.seo.edit') }}" @class(['is-active' => request()->routeIs('admin.seo.*')])><span class="admin-nav__icon" aria-hidden="true">⚙</span>تنظیمات سئو</a>
            <a href="{{ route('admin.ai-settings.edit') }}" @class(['is-active' => request()->routeIs('admin.ai-settings.*')])><span class="admin-nav__icon" aria-hidden="true">✦</span>تنظیمات هوش مصنوعی</a>
            <a href="{{ route('admin.content-readiness') }}" @class(['is-active' => request()->routeIs('admin.content-readiness')])><span class="admin-nav__icon" aria-hidden="true">✓</span>آمادگی محتوا</a>
        </nav>
        <div class="admin-side__club"><span>♛</span><div><strong>NiniStory Club</strong><small>باشگاه مشتریان</small></div></div>
        <form method="post" action="{{ route('admin.logout') }}" class="admin-side__logout">@csrf<button type="submit">خروج از پنل <span aria-hidden="true">↗</span></button></form>
    </aside>
    <button type="button" class="admin-side__backdrop" data-admin-sidebar-close aria-label="بستن منو"></button>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-toggle" aria-label="باز کردن منو" aria-controls="admin-sidebar" aria-expanded="false">☰</button>
            <form class="admin-global-search" method="get" action="{{ route('admin.products.index') }}" role="search">
                <span aria-hidden="true">⌕</span><input name="q" value="{{ request()->routeIs('admin.products.*') ? request('q') : '' }}" placeholder="جستجو در نام محصول یا SKU..." aria-label="جستجوی محصولات و کد کالا">
                <kbd>Ctrl K</kbd>
            </form>
            <div class="admin-topbar__actions">
                <a class="admin-icon-button" href="{{ route('admin.inventory.index') }}" aria-label="هشدارهای موجودی">♧<i></i></a>
                <div class="admin-profile"><span class="admin-profile__avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>مدیر فروشگاه</small></span><span aria-hidden="true">⌄</span></div>
            </div>
        </header>
        <main class="admin-main">
            @if (session('status'))<div class="admin-alert admin-alert--success" role="status"><span aria-hidden="true">✓</span>{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="admin-alert admin-alert--error" role="alert"><strong>لطفاً موارد زیر را بررسی کنید</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<script>
(() => {
    const toggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('#admin-sidebar');
    const mobileSidebar = window.matchMedia('(max-width: 850px)');
    const setSidebarOpen = (open) => {
        sidebar?.classList.toggle('is-open', open);
        document.body.classList.toggle('admin-sidebar-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };
    toggle?.addEventListener('click', () => {
        setSidebarOpen(!sidebar.classList.contains('is-open'));
    });
    document.querySelector('[data-admin-sidebar-close]')?.addEventListener('click', () => setSidebarOpen(false));
    mobileSidebar.addEventListener('change', (event) => { if (!event.matches) setSidebarOpen(false); });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.querySelector('.admin-global-search input')?.focus();
        }
        if (event.key === 'Escape') setSidebarOpen(false);
    });
})();
</script>
@stack('scripts')
</body>
</html>
