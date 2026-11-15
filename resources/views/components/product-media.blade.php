@props(['product', 'image' => null, 'loading' => 'lazy', 'class' => '', 'sizes' => '(max-width: 767px) 50vw, 25vw'])
@php
    $storedImage = $image ?: $product->images->first(fn ($candidate) => \Illuminate\Support\Facades\Storage::disk('public')->exists($candidate->path));
    $categorySlug = $product->category?->slug ?? '';
    $fallbackTone = str_contains($categorySlug, 'boys') ? 'sky' : (str_contains($categorySlug, 'baby') ? 'mint' : 'pink');
    $fallbackLabel = $product->category?->name ?: 'پوشاک کودک';
@endphp
<div class="product-media {{ $class }}">
    @if ($storedImage)
        @php($sources = app(\App\Services\ImageVariantService::class)->responsiveSources($storedImage->path))
        <img src="{{ asset('storage/'.$storedImage->path) }}" @if($sources) srcset="{{ collect($sources)->map(fn ($url, $width) => $url.' '.$width.'w')->implode(', ') }}" sizes="{{ $sizes }}" @endif alt="{{ $storedImage->alt_text ?: $product->name }}" width="{{ $storedImage->width ?: 800 }}" height="{{ $storedImage->height ?: 1000 }}" loading="{{ $loading }}" decoding="async">
    @else
        <div class="product-media__fallback product-media__fallback--{{ $fallbackTone }}" role="img" aria-label="تصویر محصول {{ $product->name }} در دسترس نیست">
            <span class="product-media__fallback-shape" aria-hidden="true">✦</span>
            <strong>{{ $fallbackLabel }}</strong>
            <small>تصویر محصول به‌زودی</small>
        </div>
    @endif
</div>
