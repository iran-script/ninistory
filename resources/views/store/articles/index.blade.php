@extends('layouts.store')
@section('title', $seo['title'])
@section('content')
<section class="mx-auto max-w-6xl px-4 py-8"><h1 class="text-3xl font-extrabold">مجله نینی‌استوری</h1><p class="mt-3 text-slate-600">راهنماهای کوتاه و کاربردی برای لباس پوشیدن کوچولوها.</p><div class="mt-8 grid gap-5 md:grid-cols-3">@forelse($articles as $article)<article class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">{{ $article->category?->name ?: 'راهنمای خرید' }}</p><h2 class="mt-2 text-xl font-bold"><a href="{{ route('articles.show', $article) }}">{{ $article->title }}</a></h2><p class="mt-3 text-sm leading-7 text-slate-600">{{ $article->summary }}</p><a class="mt-4 inline-block font-bold text-pink-700" href="{{ route('articles.show', $article) }}">ادامه مطلب</a></article>@empty<p class="rounded-2xl bg-white p-6">هنوز مقاله‌ای منتشر نشده است.</p>@endforelse</div><div class="mt-8">{{ $articles->links() }}</div></section>
@endsection
