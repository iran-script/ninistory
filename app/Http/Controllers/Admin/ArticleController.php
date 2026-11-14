<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\SeoMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.articles.index', ['articles' => Article::with('category')->latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article(['status' => 'draft']), 'categories' => ArticleCategory::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $article = new Article;
        $this->save($request, $article);

        return redirect()->route('admin.articles.index')->with('status', 'مقاله ذخیره شد.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', ['article' => $article, 'categories' => ArticleCategory::orderBy('name')->get()]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->save($request, $article);

        return redirect()->route('admin.articles.index')->with('status', 'مقاله به‌روزرسانی شد.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return back()->with('status', 'مقاله حذف شد.');
    }

    public function preview(Article $article): View
    {
        return view('store.articles.show', ['article' => $article, 'seo' => app(SeoMetadataService::class)->article($article)]);
    }

    private function save(Request $request, Article $article): void
    {
        $data = $request->validate(['article_category_id' => ['nullable', 'integer', 'exists:article_categories,id'], 'title' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'string', 'max:190', 'alpha_dash:ascii', 'unique:articles,slug,'.($article->id ?: 'NULL')], 'summary' => ['nullable', 'string', 'max:600'], 'content' => ['required', 'string', 'max:50000'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320'], 'canonical_url' => ['nullable', 'url:http,https', 'max:500'], 'robots_directive' => ['nullable', 'in:index,follow,noindex,follow,noindex,nofollow'], 'status' => ['required', 'in:draft,published']]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($article->published_at ?: now()) : null;
        $data['author_id'] = auth()->id();
        $article->fill($data)->save();
    }
}
