@extends('admin.layout')

@section('content')
    <h1 class="mb-5 text-3xl font-extrabold">{{ $category->exists ? 'ویرایش دسته‌بندی' : 'دسته‌بندی تازه' }}</h1>

    <form class="admin-card admin-form" method="post" enctype="multipart/form-data"
          action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($category->exists)
            @method('put')
        @endif

        <label>نام
            <input required name="name" value="{{ old('name', $category->name) }}">
        </label>
        <label>Slug
            <input name="slug" dir="ltr" value="{{ old('slug', $category->slug) }}">
        </label>
        <label>دسته‌ی والد
            <select name="parent_id">
                <option value="">بدون والد</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                @endforeach
            </select>
        </label>
        <label>ترتیب نمایش
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
        </label>
        <label class="admin-span">توضیحات
            <textarea name="description" rows="3">{{ old('description', $category->description) }}</textarea>
        </label>
        <label>عنوان SEO
            <input name="meta_title" value="{{ old('meta_title', $category->meta_title) }}">
        </label>
        <label>توضیح SEO
            <input name="meta_description" value="{{ old('meta_description', $category->meta_description) }}">
        </label>
        <label>Canonical URL
            <input dir="ltr" name="canonical_url" value="{{ old('canonical_url', $category->canonical_url) }}">
        </label>
        <label>دستور ربات
            <select name="robots_directive"><option value="">پیش‌فرض</option><option value="index,follow" @selected(old('robots_directive',$category->robots_directive)==='index,follow')>index,follow</option><option value="noindex,follow" @selected(old('robots_directive',$category->robots_directive)==='noindex,follow')>noindex,follow</option><option value="noindex,nofollow" @selected(old('robots_directive',$category->robots_directive)==='noindex,nofollow')>noindex,nofollow</option></select>
        </label>
        <label class="admin-span">محتوای تکمیلی SEO
            <textarea name="seo_content" rows="4">{{ old('seo_content', $category->seo_content) }}</textarea>
        </label>
        <label class="admin-span">تصویر کاور دسته‌بندی
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </label>

        @if ($category->image_path)
            <div class="admin-span flex flex-wrap items-center gap-4">
                <img class="h-32 w-48 rounded-2xl object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="{{ $category->name }}">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remove_image" value="1">
                    حذف تصویر فعلی
                </label>
            </div>
        @endif

        <label><span>فعال</span>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
        </label>
        <div class="admin-span">
            <button class="admin-button">ذخیره</button>
            <a class="admin-button admin-button--muted" href="{{ route('admin.categories.index') }}">بازگشت</a>
        </div>
    </form>
@endsection
