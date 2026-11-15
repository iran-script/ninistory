@extends('admin.layout')

@section('title', 'داشبورد | NiniStory')

@section('content')
    <section class="admin-welcome">
        <div>
            <p class="admin-eyebrow">داشبورد مدیریت · {{ now()->format('Y/m/d') }} <span aria-hidden="true">✦</span> روز خوبی برای رشد فروشگاه شماست</p>
            <h1>سلام {{ auth()->user()->name }} عزیز <span aria-hidden="true">👋</span></h1>
            <p>به پنل مدیریت NiniStory خوش آمدید. وضعیت امروز فروشگاه را اینجا ببینید.</p>
        </div>
        <span class="admin-welcome__flower" aria-hidden="true">♡</span>
    </section>

    <section class="admin-kpis" aria-label="خلاصه فروشگاه">
        <article class="admin-kpi admin-kpi--pink"><span class="admin-kpi__icon" aria-hidden="true">↗</span><p>فروش ۷ روز اخیر</p><strong>{{ number_format($salesTotal) }} <small>تومان</small></strong><span class="admin-kpi__note">از سفارش‌های پرداخت‌شده</span></article>
        <article class="admin-kpi admin-kpi--violet"><span class="admin-kpi__icon" aria-hidden="true">♙</span><p>محصولات منتشرشده</p><strong>{{ number_format($published) }}</strong><span class="admin-kpi__note">از {{ number_format($products) }} محصول</span></article>
        <article class="admin-kpi admin-kpi--blue"><span class="admin-kpi__icon" aria-hidden="true">▣</span><p>سفارش‌های امروز</p><strong>{{ number_format($todayOrders) }}</strong><span class="admin-kpi__note">ثبت‌شده در فروشگاه</span></article>
        <article class="admin-kpi admin-kpi--mint"><span class="admin-kpi__icon" aria-hidden="true">⬡</span><p>دسته‌بندی‌ها</p><strong>{{ number_format($categories) }}</strong><span class="admin-kpi__note">{{ number_format($drafts) }} پیش‌نویس محصول</span></article>
        <article class="admin-kpi admin-kpi--amber"><span class="admin-kpi__icon" aria-hidden="true">!</span><p>نیازمند رسیدگی</p><strong>{{ number_format($lowStock + $outOfStock) }}</strong><span class="admin-kpi__note">{{ number_format($lowStock) }} کم‌موجود · {{ number_format($outOfStock) }} ناموجود</span></article>
    </section>

    <section class="admin-dashboard-grid admin-dashboard-grid--charts">
        <article class="admin-panel admin-sales-panel">
            <div class="admin-panel__heading"><div><h2>روند فروش ۷ روز اخیر</h2><p>مبلغ سفارش‌های پرداخت‌شده · تومان</p></div><span class="admin-panel__range">۷ روز اخیر <span aria-hidden="true">⌄</span></span></div>
            @if ($salesTotal > 0)
                <div class="admin-chart" role="img" aria-label="نمودار فروش ۷ روز اخیر">
                    <div class="admin-chart__grid"><span>{{ number_format((int) max(1, $sales->max('revenue'))) }}</span><span>{{ number_format((int) ($sales->max('revenue') / 2)) }}</span><span>۰</span></div>
                    <svg viewBox="0 0 600 180" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="sales-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#9a5bdf" stop-opacity=".28"/><stop offset="100%" stop-color="#9a5bdf" stop-opacity="0"/></linearGradient></defs><polygon points="0,180 {{ $salesPoints }} 600,180" fill="url(#sales-fill)"/><polyline points="{{ $salesPoints }}" fill="none" stroke="#9855dc" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>@foreach ($sales as $index => $point)<circle cx="{{ $index * (600 / max(1, $sales->count() - 1)) }}" cy="{{ 160 - ($point['revenue'] / max(1, $sales->max('revenue')) * 132) }}" r="4" fill="white" stroke="#9855dc" stroke-width="3" vector-effect="non-scaling-stroke"/>@endforeach</svg>
                    <div class="admin-chart__labels">@foreach ($sales as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
                </div>
            @else
                <div class="admin-empty-state"><span aria-hidden="true">↗</span><strong>هنوز فروشی در این بازه ثبت نشده</strong><p>با ثبت سفارش پرداخت‌شده، روند فروش اینجا نمایش داده می‌شود.</p></div>
            @endif
        </article>
        <article class="admin-panel admin-stock-panel">
            <div class="admin-panel__heading"><div><h2>وضعیت موجودی کالاها</h2><p>بر اساس تنوع‌های فعال</p></div><a href="{{ route('admin.inventory.index') }}">مدیریت موجودی ←</a></div>
            @php($inventoryTotal = $inStock + $outOfStock)
            @if ($inventoryTotal > 0)
                <div class="admin-stock-summary"><div class="admin-donut" style="--stock-percent: {{ round($inStock / $inventoryTotal * 100) }}%"><div><strong>{{ number_format($inventoryTotal) }}</strong><span>تنوع فعال</span></div></div><div class="admin-legend"><p><i class="is-mint"></i><span>موجود</span><strong>{{ number_format($inStock) }}</strong></p><p><i class="is-rose"></i><span>ناموجود</span><strong>{{ number_format($outOfStock) }}</strong></p><p><i class="is-amber"></i><span>کم‌موجود</span><strong>{{ number_format($lowStock) }}</strong></p></div></div>
            @else
                <div class="admin-empty-state"><span aria-hidden="true">⬡</span><strong>تنوع فعالی برای نمایش نیست</strong><p>پس از ساخت تنوع محصول، وضعیت موجودی آن اینجا دیده می‌شود.</p></div>
            @endif
        </article>
    </section>

    <section class="admin-dashboard-grid admin-dashboard-grid--bottom">
        <article class="admin-panel">
            <div class="admin-panel__heading"><div><h2>آخرین سفارش‌ها</h2><p>سفارش‌های تازه ثبت‌شده</p></div><a href="{{ route('admin.orders.index') }}">مشاهده همه ←</a></div>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>کد سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت پرداخت</th></tr></thead><tbody>
                @forelse ($recentOrders as $order)<tr><td dir="ltr">#{{ $order->number }}</td><td>{{ $order->recipient_name }}</td><td>{{ number_format($order->total_toman) }} تومان</td><td><span @class(['admin-status', 'is-paid' => $order->payment_status === 'paid', 'is-pending' => $order->payment_status === 'pending', 'is-other' => ! in_array($order->payment_status, ['paid', 'pending'], true)])>{{ $order->paymentLabel() }}</span></td></tr>
                @empty<tr><td colspan="4"><div class="admin-inline-empty">هنوز سفارشی ثبت نشده است.</div></td></tr>@endforelse
            </tbody></table></div>
        </article>
        <article class="admin-panel">
            <div class="admin-panel__heading"><div><h2>محصولات پرفروش</h2><p>بر پایه تعداد فروش ثبت‌شده</p></div><a href="{{ route('admin.products.index') }}">همه محصولات ←</a></div>
            <div class="admin-best-sellers">
                @forelse ($bestSellers as $index => $product)<div class="admin-best-seller"><span class="admin-best-seller__rank">{{ $index + 1 }}</span><div class="admin-best-seller__name"><strong>{{ $product->product_name }}</strong><small>{{ number_format($product->units_sold) }} عدد فروخته‌شده</small></div><strong class="admin-best-seller__revenue">{{ number_format($product->revenue) }} <small>تومان</small></strong></div>
                @empty<div class="admin-empty-state admin-empty-state--compact"><span aria-hidden="true">◇</span><strong>داده فروش کافی نیست</strong><p>پس از ثبت فروش، محصولات پرفروش نمایش داده می‌شوند.</p></div>@endforelse
            </div>
        </article>
    </section>

    <section class="admin-panel admin-changes-panel">
        <div class="admin-panel__heading"><div><h2>تغییرات اخیر موجودی</h2><p>ردیابی آخرین عملیات انجام‌شده</p></div><a href="{{ route('admin.inventory.index') }}">دفتر موجودی ←</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>محصول و تنوع</th><th>موجودی قبل</th><th>تغییر</th><th>موجودی بعد</th><th>دلیل</th><th>زمان</th></tr></thead><tbody>
            @forelse ($changes as $change)<tr><td>{{ $change->variant?->product?->name }} <small>{{ $change->variant?->color_name }} / {{ $change->variant?->size }}</small></td><td>{{ number_format($change->quantity_before) }}</td><td @class(['is-positive' => $change->quantity_delta > 0, 'is-negative' => $change->quantity_delta < 0])>{{ $change->quantity_delta > 0 ? '+' : '' }}{{ number_format($change->quantity_delta) }}</td><td>{{ number_format($change->quantity_after) }}</td><td>{{ $change->reason }}</td><td>{{ $change->created_at->format('Y/m/d H:i') }}</td></tr>
            @empty<tr><td colspan="6"><div class="admin-inline-empty">هنوز تغییر موجودی ثبت نشده است.</div></td></tr>@endforelse
        </tbody></table></div>
    </section>

    <aside class="admin-quick-actions" aria-label="دسترسی سریع"><strong>دسترسی سریع</strong><a href="{{ route('admin.products.create') }}"><span aria-hidden="true">＋</span> افزودن محصول</a><a href="{{ route('admin.categories.create') }}"><span aria-hidden="true">▱</span> افزودن دسته‌بندی</a><a href="{{ route('admin.inventory.index') }}"><span aria-hidden="true">⬡</span> مدیریت موجودی</a><a href="{{ route('admin.orders.index') }}"><span aria-hidden="true">▣</span> مشاهده سفارش‌ها</a></aside>
@endsection
