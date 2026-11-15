@extends('admin.layout')
@section('content')
<div class="mb-5 flex items-center justify-between"><h1 class="text-3xl font-extrabold">آمادگی محتوای فروشگاه</h1><a class="admin-button" href="{{ route('admin.products.index') }}">مدیریت محصولات</a></div>
<div class="admin-grid">
@foreach(['real_products'=>'محصولات غیر دمویی','demo_products'=>'محصولات دمویی','missing_photos'=>'بدون عکس','missing_sizes'=>'بدون اندازه','missing_material'=>'بدون جنس/ترکیب','seo_review'=>'نیازمند بازبینی SEO'] as $key=>$label)<section class="admin-card"><div class="text-sm text-gray-600">{{ $label }}</div><div class="mt-2 text-3xl font-black">{{ $counts[$key] }}</div></section>@endforeach
</div>
<section class="admin-card mt-5"><h2 class="mb-3 text-xl font-bold">موارد قابل اقدام</h2><ul class="list-disc space-y-2 pr-6"><li>{{ $categoriesMissingGuides }} دسته فعال بدون راهنمای خرید</li><li>{{ $articlesAwaitingReview }} مقاله در وضعیت پیش‌نویس یا نیازمند بررسی</li><li>{{ $shippingRates }} نرخ ارسال فعال پیکربندی شده</li><li>{{ $missingPages->count() }} صفحه اعتماد/سیاست هنوز ایجاد نشده است</li></ul></section>
<section class="admin-card mt-5"><h2 class="mb-3 text-xl font-bold">صفحه‌های کسب‌وکار</h2><div class="grid gap-2 sm:grid-cols-2">@foreach(['about'=>'درباره کیدوکی','contact'=>'تماس با ما','buying-guide'=>'راهنمای خرید','shipping'=>'روش‌های ارسال','returns'=>'شرایط مرجوعی','privacy'=>'حریم خصوصی','faq'=>'پرسش‌های متداول'] as $slug=>$title)<a class="rounded-xl border p-3" href="{{ route('admin.content-pages.edit',$slug) }}">{{ $title }} <span class="text-sm text-gray-500">({{ $pages->get($slug)?->status ?? 'ایجاد نشده' }})</span></a>@endforeach</div></section>
@endsection
