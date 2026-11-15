@extends('admin.layout')
@section('title', 'ممیزی محتوای سئو | کیدوکی')
@section('content')
<h1 class="mb-5 text-3xl font-extrabold">ممیزی محتوای سئو</h1>
<p class="mb-5 rounded-2xl bg-amber-50 p-4 text-sm">این گزارش نقص‌های واقعی داده و پیشنهادهای ویراستاری را جدا می‌کند و امتیاز رتبه‌بندی گوگل نیست.</p>
<div class="admin-card overflow-x-auto"><table class="admin-table"><thead><tr><th>نوع</th><th>شدت</th><th>صفحه/موضوع</th><th>شرح</th></tr></thead><tbody>@forelse($findings as $finding)<tr><td>{{ $finding['type'] }}</td><td>{{ $finding['severity'] }}</td><td>{{ $finding['subject'] }}</td><td>{{ $finding['message'] }}</td></tr>@empty<tr><td colspan="4">موردی برای بررسی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
@endsection
