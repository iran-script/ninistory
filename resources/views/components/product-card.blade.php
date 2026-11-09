@props(['product'])
@php
    $previewKey = match (true) {
        str_contains($product->slug, 'sunny') => 'floral-dress',
        str_contains($product->slug, 'girls') => 'pink-coat',
        str_contains($product->slug, 'boys') => 'denim-overalls',
        str_contains($product->slug, 'baby') => 'teddy-sweater',
        default => null,
    };
    $previewOnly = app()->environment(['local', 'testing']);
    $storedImage = $product->images->first(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->path));
    $previewPath = $previewKey ? 'images/products/'.$previewKey.'.jpg' : null;
    $usingPreview = $previewOnly && ! $storedImage && $previewPath
        && is_file(storage_path('app/demo-preview/'.$previewPath));
@endphp
<article class="product-card">
    <a href="{{ route('products.show', $product) }}" class="product-card__link">
        <div class="product-card__image">
            @if($storedImage)
                <img src="{{ asset('storage/'.$storedImage->path) }}" alt="{{ $storedImage->alt_text }}" loading="lazy">
            @elseif($usingPreview)
                <img src="{{ route('preview.asset', ['path'=>'images/products/'.$previewKey.'.jpg']) }}" alt="تصویر نمایشی محصول {{ $product->name }}؛ عکس نمونه و نه تصویر کالای واقعی" loading="lazy">
                <span class="preview-label">تصویر نمایشی</span>
            @else
                <div class="product-card__unavailable" aria-label="تصویر محصول در دسترس نیست"><span aria-hidden="true">♡</span></div>
            @endif
            @if($product->is_featured)<span class="product-card__badge">پرفروش</span>@endif
        </div>
        <div class="product-card__details">
            <h3>{{ $product->name }}</h3>
            @if($product->variants->isNotEmpty())
                <p>{{ number_format($product->variants->min('price_toman')) }} <span>تومان</span></p>
            @else
                <p class="product-card__contact">برای قیمت تماس بگیرید</p>
            @endif
            <span class="product-card__colors" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
    </a>
</article>
