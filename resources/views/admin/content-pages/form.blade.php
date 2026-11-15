@extends('admin.layout')
@section('content')
<h1 class="mb-5 text-3xl font-extrabold">{{ $page->title }}</h1>
<form class="admin-card admin-form" method="post" action="{{ route('admin.content-pages.update',$page->slug) }}">@csrf @method('put')
<label class="admin-span">عنوان<input required name="title" value="{{ old('title',$page->title) }}"></label>
<label>وضعیت<select name="status">@foreach(['draft'=>'پیش‌نویس','needs_review'=>'نیازمند بررسی','approved'=>'تأییدشده','published'=>'منتشرشده'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$page->status)===$value)>{{ $label }}</option>@endforeach</select></label>
<label>عنوان SEO<input name="meta_title" value="{{ old('meta_title',$page->meta_title) }}"></label>
<label class="admin-span">متن تأییدشده صفحه<textarea name="body" rows="12">{{ old('body',$page->body) }}</textarea></label>
<label class="admin-span">توضیح SEO<textarea name="meta_description" rows="3">{{ old('meta_description',$page->meta_description) }}</textarea></label>
<div class="admin-span"><button class="admin-button">ذخیره صفحه</button><a class="admin-button admin-button--muted" href="{{ route('admin.content-readiness') }}">بازگشت</a></div>
</form>
@endsection
