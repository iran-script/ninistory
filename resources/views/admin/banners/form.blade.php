@extends('admin.layout')

@section('content')
    <h1 class="mb-5 text-3xl font-extrabold">{{ $banner->exists ? 'ویرایش بنر' : 'بنر جدید' }}</h1>

    <form class="admin-card admin-form" method="post" enctype="multipart/form-data"
          action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}">
        @csrf
        @if ($banner->exists)
            @method('put')
        @endif

        <label>عنوان
            <input required name="title" value="{{ old('title', $banner->title) }}">
        </label>
        <label>زیرعنوان
            <input name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}">
        </label>
        <label>جایگاه
            <select required name="position">
                @foreach ($positions as $key => $label)
                    <option value="{{ $key }}" @selected(old('position', $banner->position ?: 'hero') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>ترتیب نمایش
            <input required type="number" min="0" max="65535" name="sort_order" value="{{ old('sort_order', $banner->sort_order ?? 0) }}">
        </label>
        <label>مدت نمایش اسلاید (ثانیه)
            <input required type="number" min="3" max="15" name="duration_seconds" value="{{ old('duration_seconds', $banner->duration_seconds ?? 6) }}" data-banner-duration>
            <span class="mt-1 block text-xs text-slate-500">بین ۳ تا ۱۵ ثانیه؛ برای هر اسلاید مستقل است.</span>
        </label>
        <label>جای متن روی تصویر
            <select required name="text_position" data-banner-text-position>
                <option value="right" @selected(old('text_position', $banner->text_position ?? 'right') === 'right')>راست</option>
                <option value="center" @selected(old('text_position', $banner->text_position ?? 'right') === 'center')>مرکز</option>
                <option value="left" @selected(old('text_position', $banner->text_position ?? 'right') === 'left')>چپ</option>
            </select>
        </label>
        <label>شدت لایه خوانایی متن
            <select required name="overlay_style">
                <option value="soft" @selected(old('overlay_style', $banner->overlay_style ?? 'soft') === 'soft')>ملایم</option>
                <option value="strong" @selected(old('overlay_style', $banner->overlay_style ?? 'soft') === 'strong')>قوی</option>
                <option value="none" @selected(old('overlay_style', $banner->overlay_style ?? 'soft') === 'none')>بدون لایه</option>
            </select>
        </label>
        <label>متن دکمه
            <input name="cta_label" value="{{ old('cta_label', $banner->cta_label) }}">
        </label>
        <label>مقصد داخلی دکمه
            <input name="cta_url" dir="ltr" placeholder="/products" value="{{ old('cta_url', $banner->cta_url) }}">
        </label>
        <label class="admin-span">متن جایگزین تصویر
            <input required name="alt_text" value="{{ old('alt_text', $banner->alt_text) }}">
        </label>
        <label>تصویر دسکتاپ (حداکثر ۵ مگابایت)
            <span class="mt-1 block text-xs text-slate-500">نسبت پیشنهادی: حدود ۲٫۲:۱ تا ۲٫۶:۱</span>
            <input {{ $banner->exists ? '' : 'required' }} type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp" data-banner-image-input="desktop" data-banner-preview="desktop">
        </label>
        <label>تصویر موبایل (اختیاری)
            <span class="mt-1 block text-xs text-slate-500">نسبت پیشنهادی: ۴:۵ یا ۳:۴</span>
            <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp" data-banner-image-input="mobile" data-banner-preview="mobile">
        </label>

        @if ($banner->exists)
            <div class="admin-span grid gap-3 md:grid-cols-2">
                <img class="max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->desktop_image_path) }}" alt="{{ $banner->alt_text }}">
                @if ($banner->mobile_image_path)
                    <img class="max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->mobile_image_path) }}" alt="{{ $banner->alt_text }}">
                @endif
                <img class="hidden max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" data-banner-preview-image="desktop" alt="پیش‌نمایش تصویر دسکتاپ">
                <img class="hidden max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" data-banner-preview-image="mobile" alt="پیش‌نمایش تصویر موبایل">
            </div>
        @else
            <div class="admin-span grid gap-3 md:grid-cols-2">
                <img class="hidden max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" data-banner-preview-image="desktop" alt="پیش‌نمایش تصویر دسکتاپ">
                <img class="hidden max-h-56 w-full rounded-2xl bg-[#fff7f4] object-contain" data-banner-preview-image="mobile" alt="پیش‌نمایش تصویر موبایل">
            </div>
        @endif

        <div class="admin-span rounded-2xl border border-[#efded7] bg-[#fff8f5] p-4" data-banner-caption-preview>
            <p class="text-xs font-bold text-[#a85c72]" data-banner-preview-subtitle>{{ old('subtitle', $banner->subtitle) ?: 'زیرعنوان کمپین' }}</p>
            <p class="mt-1 text-xl font-extrabold text-[#70213f]" data-banner-preview-title>{{ old('title', $banner->title) ?: 'عنوان کمپین' }}</p>
            <span class="mt-3 inline-flex rounded-full bg-[#c92e70] px-4 py-2 text-sm font-bold text-white">پیش‌نمایش متن اسلاید</span>
        </div>

        <label>شروع انتشار
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($banner->starts_at)->format('Y-m-d\TH:i')) }}">
        </label>
        <label>پایان انتشار
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($banner->ends_at)->format('Y-m-d\TH:i')) }}">
        </label>
        <label><span>فعال</span>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))>
        </label>

        <div class="admin-span">
            <button class="admin-button">ذخیره بنر</button>
            <a class="admin-button admin-button--muted" href="{{ route('admin.banners.index') }}">بازگشت</a>
        </div>
    </form>
@endsection
