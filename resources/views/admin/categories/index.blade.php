@extends('admin.layout')

@section('content')
    <div class="category-workspace__heading">
        <div>
            <p class="admin-eyebrow">مدیریت ساختار فروشگاه</p>
            <h1>دسته‌بندی‌ها</h1>
            <p class="admin-help">ساختار را مرتب کنید، وضعیت و وابستگی‌ها را ببینید و دسته‌ها را بدون رفت‌وبرگشت مدیریت کنید.</p>
        </div>
        <div class="category-workspace__actions">
            <button type="button" class="admin-button" data-category-new>＋ دسته‌بندی تازه</button>
            <a class="admin-button admin-button--muted" href="{{ route('admin.homepage-categories.edit') }}">تنظیم دسته‌های صفحه اصلی</a>
        </div>
    </div>

    @if (session('status'))
        <div class="admin-alert admin-alert--success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-alert admin-alert--error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="category-workspace" data-category-workspace>
        <div class="category-tree-panel admin-card">
            <div class="category-tree-panel__heading">
                <div><h2>ساختار دسته‌ها</h2><span>{{ $categories->count() }} دسته ثبت شده</span></div>
                <span class="category-tree-panel__hint">برای جابه‌جایی بکشید یا با صفحه‌کلید مرتب کنید</span>
            </div>
            <label class="category-search">⌕ <span class="sr-only">جست‌وجوی دسته‌ها</span><input type="search" placeholder="جست‌وجوی نام یا slug…" data-category-search></label>
            <div class="category-tree" data-category-tree>
                @if ($tree)
                    <ul class="category-tree__list" data-category-level data-parent-id="">
                        @include('admin.categories._tree-node', ['nodes' => $tree])
                    </ul>
                @else
                    <div class="admin-empty-state"><span>▱</span><strong>هنوز دسته‌ای ساخته نشده</strong><p>اولین دسته را بسازید تا ساختار فروشگاه شکل بگیرد.</p></div>
                @endif
            </div>
            <form method="post" action="{{ route('admin.categories.reorder') }}" data-category-reorder-form>
                @csrf
                <div data-category-reorder-inputs></div>
                <button type="submit" class="admin-button admin-button--soft">ذخیره ترتیب و جایگاه‌ها</button>
            </form>
        </div>

        <aside class="category-editor-panel admin-card" data-category-editor-panel>
            <div class="category-editor-panel__heading"><div><p class="admin-eyebrow">ویرایش سریع</p><h2 data-category-editor-title>یک دسته را انتخاب کنید</h2></div><span class="category-editor-panel__dot" aria-hidden="true"></span></div>
            <p class="admin-help" data-category-editor-help>از درخت سمت راست یک دسته را انتخاب کنید یا یک دسته تازه بسازید.</p>
            <form class="admin-form" method="post" enctype="multipart/form-data" action="{{ route('admin.categories.store') }}" data-category-editor-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-category-method>
                <label>نام دسته<input required name="name" data-category-field="name" value="{{ old('name') }}"></label>
                <label>Slug<input dir="ltr" name="slug" data-category-field="slug" value="{{ old('slug') }}" placeholder="نام-دسته"></label>
                <label>دسته والد<select name="parent_id" data-category-field="parent_id"><option value="">بدون والد</option>@foreach ($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
                <label>ترتیب نمایش<input type="number" min="0" name="sort_order" data-category-field="sort_order" value="{{ old('sort_order', 0) }}"></label>
                <label class="admin-span">توضیحات<textarea rows="4" name="description" data-category-field="description">{{ old('description') }}</textarea></label>
                <label class="admin-span category-image-upload">تصویر دسته <span class="admin-help">JPG، PNG یا WebP؛ حداقل ۴۰۰×۳۰۰</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-category-image-input><img class="category-editor-image hidden" data-category-image-preview alt=""></label>
                <label class="category-toggle"><input type="checkbox" name="is_active" value="1" checked data-category-field="is_active"><span>نمایش دسته در فروشگاه و منو</span></label>
                <div class="admin-span category-editor-panel__footer"><button class="admin-button" type="submit">ذخیره دسته</button><a class="admin-button admin-button--muted hidden" data-category-full-edit href="#">ویرایش کامل SEO و راهنمای سایز</a></div>
            </form>
            <div class="category-impact hidden" data-category-impact></div>
            <div class="category-editor-panel__danger hidden" data-category-danger><form method="post" data-category-delete-form>@csrf @method('delete')<button type="submit" class="admin-button admin-button--danger">حذف امن دسته</button></form><p>حذف فقط وقتی ممکن است که محصول، زیر‌دسته یا جایگاه صفحه اصلی وابسته‌ای وجود نداشته باشد.</p></div>
        </aside>
    </section>
@endsection
