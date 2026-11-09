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
            <input {{ $banner->exists ? '' : 'required' }} type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp">
        </label>
        <label>تصویر موبایل (اختیاری)
            <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp">
        </label>

        @if ($banner->exists)
            <div class="admin-span grid gap-3 md:grid-cols-2">
                <img class="max-h-56 w-full rounded-2xl object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->desktop_image_path) }}" alt="{{ $banner->alt_text }}">
                @if ($banner->mobile_image_path)
                    <img class="max-h-56 w-full rounded-2xl object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->mobile_image_path) }}" alt="{{ $banner->alt_text }}">
                @endif
            </div>
        @endif

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
