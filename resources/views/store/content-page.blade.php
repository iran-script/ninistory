@extends('layouts.store')
@section('title', $contentPage->meta_title ?: $contentPage->title)
@section('content')
<main class="mx-auto max-w-4xl px-4 py-10"><nav class="mb-5 text-sm text-slate-600"><a href="{{ route('home') }}">کیدوکی</a> / {{ $contentPage->title }}</nav><article class="prose prose-pink max-w-none text-right"><h1>{{ $contentPage->title }}</h1><div class="whitespace-pre-line">{{ $contentPage->body }}</div></article></main>
@endsection
