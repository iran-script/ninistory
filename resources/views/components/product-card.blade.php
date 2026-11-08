@props(['product'])
@php
    $previewKey = match (true) {
        str_contains($product->slug, 'sunny') => 'floral-dress',
        str_contains($product->slug, 'cotton') => 'teddy-sweater',
        str_contains($product->slug, 'girls') => 'pink-coat',
        str_contains($product->slug, 'boys') => 'denim-overalls',
        default => 'green-hoodie',
    };
    $previewOnly = app()->environment(['local', 'testing']);
    $usingPreview = $previewOnly && $product->images->isEmpty();
@endphp
<article class="group relative overflow-hidden rounded-2xl border border-[#eee5e5] bg-white shadow-[0_3px_12px_rgba(74,42,48,.08)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(74,42,48,.13)]">
    <a href="{{ route('products.show', $product) }}" class="block">
        <div class="relative aspect-[4/4.15] overflow-hidden bg-[#fbf2ef]">
            @if($product->images->isNotEmpty())
                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ asset('storage/'.$product->images->first()->path) }}" alt="{{ $product->images->first()->alt_text }}" loading="lazy">
            @elseif($previewOnly)
                <img class="h-full w-full object-cover" src="{{ route('preview.asset', ['path'=>'images/products/'.$previewKey.'.jpg']) }}" alt="تصویر نمایشی محصول؛ با کالای واقعی تطبیق ندارد" loading="lazy">
                <span class="absolute bottom-2 start-2 rounded-full bg-white/90 px-2 py-1 text-[10px] text-[#725b5a]">نمایش محلی</span>
            @else
                <div class="flex h-full flex-col items-center justify-center gap-2 text-sm text-[var(--color-muted)]"><svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>تصویر محصول</div>
            @endif
            @unless($usingPreview)<button type="button" class="absolute end-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-xl shadow" aria-label="افزودن {{ $product->name }} به علاقه‌مندی‌ها">♡</button>@if($product->is_featured)<span class="absolute start-2 top-2 rounded-full bg-[#ef9d27] px-2.5 py-1 text-[11px] font-bold text-white">پرفروش</span>@endif @endunless
        </div>
        <div class="p-3 md:p-4"><h3 class="line-clamp-2 min-h-11 text-sm font-semibold text-[#30272a] md:text-base">{{ $product->name }}</h3>
            @if($product->variants->isNotEmpty())<p class="mt-2 font-bold text-[var(--color-action)]">{{ number_format($product->variants->min('price_toman')) }} <span class="text-xs">تومان</span></p>@else<p class="mt-2 text-sm text-[var(--color-muted)]">برای قیمت تماس بگیرید</p>@endif
            <div class="mt-2 flex gap-1.5" aria-hidden="true"><i class="h-3 w-3 rounded-full bg-[#f3b6c4]"></i><i class="h-3 w-3 rounded-full bg-[#e8c5a8]"></i><i class="h-3 w-3 rounded-full bg-[#aab6bb]"></i></div>
        </div>
    </a>
</article>
