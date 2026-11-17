@props(['product'])
@php
    $activeVariants = $product->variants->where('is_active', true)->whereNull('archived_at');
    $available = $activeVariants->sum(fn ($variant) => $variant->available_quantity);
    $price = $activeVariants->min('price_toman');
    $compareAt = $activeVariants->where('compare_at_price_toman', '>', $price ?? 0)->max('compare_at_price_toman');
    $colors = $activeVariants->unique('color_name')->take(5);
@endphp
<article class="product-card">
    <a href="{{ route('products.show', $product) }}" class="product-card__link">
        <div class="product-card__image">
            <x-product-media :product="$product" />
            @if($product->is_featured)<span class="product-card__badge">منتخب کیدوکی</span>@endif
            @if($compareAt && $price && $compareAt > $price)<span class="product-card__badge product-card__badge--offer">تخفیف</span>@endif
            @if($available < 1)<span class="product-card__stock-badge">ناموجود</span>@endif
        </div>
        <div class="product-card__details">
            <h3>{{ $product->name }}</h3>
            @if($price)
                <p>@toman($price)</p>
            @else
                <p class="product-card__contact">برای قیمت تماس بگیرید</p>
            @endif
            @if($colors->isNotEmpty())<span class="product-card__colors" aria-label="رنگ‌های موجود">@foreach($colors as $color)<i style="background-color: {{ $color->color_hex ?: '#e7c4c8' }}" title="{{ $color->color_name }}"></i>@endforeach</span>@endif
        </div>
    </a>
</article>
