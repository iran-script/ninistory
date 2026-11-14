<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\SeoMetadataService;
use App\Services\SeoSchemaService;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('store.articles.index', ['articles' => Article::published()->with('category')->latest('published_at')->paginate(12), 'seo' => app(SeoMetadataService::class)->make('مجله لباس کودک | نینی‌استوری', 'راهنماهای کاربردی برای انتخاب، خرید و نگهداری لباس کودک و نوزاد.', route('articles.index'))]);
    }

    public function show(Article $article): View
    {
        abort_unless($article->status === 'published' && $article->published_at?->isPast(), 404);

        return view('store.articles.show', ['article' => $article->load(['category', 'author']), 'seo' => app(SeoMetadataService::class)->article($article), 'schemas' => [app(SeoSchemaService::class)->article($article), app(SeoSchemaService::class)->breadcrumb([['name' => 'خانه', 'url' => route('home')], ['name' => 'مجله', 'url' => route('articles.index')], ['name' => $article->title, 'url' => route('articles.show', $article)]])]]);
    }
}
