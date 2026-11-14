@props(['product'])
@php
    $available = $product->variants->where('is_active', true)->sum(fn ($variant) => $variant->available_quantity);
    $price = $product->variants->where('is_active', true)->min('price_toman');
@endphp
<article class="product-card">
    <a href="{{ route('products.show', $product) }}" class="product-card__link">
        <div class="product-card__image">
            <x-product-media :product="$product" />
            @if($product->is_featured)<span class="product-card__badge">پرفروش</span>@endif
            @if($available < 1)<span class="product-card__stock-badge">ناموجود</span>@endif
        </div>
        <div class="product-card__details">
            <h3>{{ $product->name }}</h3>
            @if($price)
                <p>{{ number_format($price) }} <span>تومان</span></p>
            @else
                <p class="product-card__contact">برای قیمت تماس بگیرید</p>
            @endif
            <span class="product-card__colors" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
    </a>
</article>
