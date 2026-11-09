@extends('admin.layout')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-3xl font-extrabold">مدیریت بنرها</h1>
        <a class="admin-button" href="{{ route('admin.banners.create') }}">بنر جدید</a>
    </div>

    <section class="admin-card admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>تصویر</th><th>عنوان</th><th>جایگاه</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
                @forelse ($banners as $banner)
                    <tr>
                        <td><img class="h-16 w-28 rounded-lg object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->desktop_image_path) }}" alt="{{ $banner->alt_text }}"></td>
                        <td>{{ $banner->title }}</td>
                        <td>{{ $positions[$banner->position] ?? $banner->position }}</td>
                        <td>{{ $banner->sort_order }}</td>
                        <td>{{ $banner->is_active ? 'فعال' : 'غیرفعال' }}</td>
                        <td class="admin-actions">
                            <a href="{{ route('admin.banners.edit', $banner) }}">ویرایش</a>
                            <form method="post" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('بنر حذف شود؟')">
                                @csrf
                                @method('delete')
                                <button class="text-red-700">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">هنوز بنری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $banners->links() }}
    </section>
@endsection
