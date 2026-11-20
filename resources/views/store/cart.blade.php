@extends('layouts.store')

@section('title', 'سبد خرید | کیدوکی')

@section('content')
    <section class="cart-page mx-auto w-full max-w-4xl px-4 py-8 sm:py-12">
        <h1 class="text-2xl font-bold sm:text-3xl">سبد خرید</h1>
        @if(session('status'))
            <p role="status" class="mt-4 rounded-xl bg-[var(--color-mint)] p-3">{{ session('status') }}</p>
        @endif

        @if($items->isEmpty())
            <div class="mt-5 rounded-2xl bg-white p-6 sm:p-8">
                <p>سبدت هنوز خالیه؛ یه قصه خوش‌پوشی انتخاب کنیم؟</p>
                <a class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl bg-[var(--color-action)] px-6 py-3 font-bold text-white" href="{{ route('products.index') }}">دیدن لباس‌ها <x-icon name="arrow-left" size="16" /></a>
            </div>
        @else
            <div class="mt-5 grid gap-3">
                @foreach($items as $item)
                    @php($variant = $item['variant'])
                    @php($cover = $variant->product->images->first())
                    <article class="cart-item grid min-w-0 grid-cols-[72px_minmax(0,1fr)] items-center gap-3 rounded-2xl bg-white p-3 sm:grid-cols-[72px_minmax(0,1fr)_auto] sm:gap-4 sm:p-4">
                        <div class="cart-item__image grid h-[72px] w-[72px] place-items-center overflow-hidden rounded-xl bg-[#fbf3ef]">
                            @if($cover)<img class="h-full w-full object-contain" src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $variant->product->name }}" loading="lazy">@else<span class="text-xs text-slate-400">بدون تصویر</span>@endif
                        </div>
                        <div class="min-w-0">
                            <h2 class="break-words font-bold">{{ $variant->product->name }}</h2>
                            <p class="mt-1 break-words text-sm text-slate-600">{{ $variant->color_name }} · {{ $variant->size }} · {{ number_format($variant->price_toman) }} تومان</p>
                            <form action="{{ route('cart.update', $variant) }}" method="post" class="mt-3 flex flex-wrap items-end gap-2">
                                @csrf @method('PATCH')
                                <label class="grid gap-1 text-xs">تعداد <input class="cart-quantity w-20 rounded-lg border p-2 text-base" type="number" min="1" max="10" name="quantity" value="{{ $item['quantity'] }}"></label>
                                <button class="min-h-11 rounded-lg bg-[var(--color-brown)] px-3 py-2 text-sm text-white">به‌روزرسانی</button>
                            </form>
                        </div>
                        <form action="{{ route('cart.remove', $variant) }}" method="post" class="cart-item__remove col-span-2 justify-self-start sm:col-span-1 sm:justify-self-end">@csrf @method('DELETE')<button class="min-h-11 rounded-lg px-3 text-red-700 underline">حذف</button></form>
                    </article>
                @endforeach
            </div>
            <div class="cart-total mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 font-bold sm:p-5">
                <span>جمع کالاها</span>
                <span>{{ number_format($items->sum(fn ($item) => $item['variant']->price_toman * $item['quantity'])) }} تومان</span>
            </div>
            <a class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[var(--color-action)] px-6 py-3 text-center font-bold text-white sm:w-auto" href="{{ route('checkout') }}">ادامه و ثبت نشانی <x-icon name="arrow-left" size="16" /></a>
        @endif
    </section>
@endsection
