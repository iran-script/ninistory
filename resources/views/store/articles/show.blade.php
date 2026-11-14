@extends('layouts.store')
@section('title', $seo['title'])
@section('content')
<article class="mx-auto max-w-3xl px-4 py-8"><nav class="text-sm text-slate-500"><a href="{{ route('articles.index') }}">مجله</a> / {{ $article->title }}</nav><h1 class="mt-5 text-3xl font-extrabold">{{ $article->title }}</h1><p class="mt-3 text-slate-500">{{ $article->published_at?->format('Y/m/d') }}</p>@if($article->cover_image_path)<img class="mt-6 aspect-[16/9] w-full rounded-3xl object-cover" src="{{ asset('storage/'.$article->cover_image_path) }}" alt="{{ $article->title }}" width="1200" height="675">@endif<div class="mt-8 whitespace-pre-line leading-9 text-slate-700">{{ $article->content }}</div></article>
@endsection
