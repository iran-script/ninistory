<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoMetadataService
{
    public function __construct(private Request $request) {}

    public function defaults(): array
    {
        $settings = SeoSetting::query()->first();

        return $this->make(
            $settings?->default_title ?: 'نینی‌استوری | خرید لباس کودک و نوزاد',
            $settings?->default_description ?: 'نینی‌استوری؛ فروشگاه اینترنتی لباس کودک و نوزاد با انتخاب‌های لطیف و دوست‌داشتنی برای کوچولوها.',
            url('/'),
            $this->isIndexableEnvironment() ? 'index,follow' : 'noindex,nofollow'
        );
    }

    public function home(): array
    {
        $settings = SeoSetting::query()->first();

        return $this->make($settings?->default_title ?: 'خرید لباس بچگانه | نینی‌استوری', $settings?->default_description ?: 'خرید لباس بچگانه، لباس دخترانه، پسرانه و نوزادی از نینی‌استوری؛ قصه‌های قشنگ کوچولوها.', url('/'), $this->indexingDirective());
    }

    public function product(Product $product): array
    {
        $description = $product->meta_description ?: Str::limit(strip_tags($product->short_description ?: $product->description ?: 'خرید '.$product->name.' از نینی‌استوری.'), 155);

        return $this->make($product->meta_title ?: $product->name.' | خرید لباس کودک | نینی‌استوری', $description, $product->canonical_url ?: route('products.show', $product), $product->robots_directive ?: $this->indexingDirective($product->is_active));
    }

    public function category(Category $category, bool $hasProducts = true): array
    {
        $description = $category->meta_description ?: Str::limit(strip_tags($category->description ?: 'خرید '.$category->name.' از فروشگاه لباس کودک نینی‌استوری.'), 155);

        return $this->make($category->meta_title ?: 'خرید '.$category->name.' | نینی‌استوری', $description, $category->canonical_url ?: route('categories.show', $category), $category->robots_directive ?: $this->indexingDirective($category->is_active && $hasProducts));
    }

    public function article(Article $article): array
    {
        return $this->make($article->meta_title ?: $article->title.' | مجله نینی‌استوری', Str::limit(strip_tags($article->meta_description ?: $article->summary ?: $article->content), 155), $article->canonical_url ?: route('articles.show', $article), $article->robots_directive ?: $this->indexingDirective($article->status === 'published'));
    }

    public function make(string $title, string $description, string $canonical, string $robots = 'index,follow', ?string $image = null): array
    {
        return ['title' => Str::limit(trim($title), 65, ''), 'description' => Str::limit(trim(strip_tags($description)), 160, ''), 'canonical' => $canonical, 'robots' => $robots, 'og_title' => trim($title), 'og_description' => Str::limit(trim(strip_tags($description)), 200, ''), 'og_image' => $image];
    }

    private function indexingDirective(bool $eligible = true): string
    {
        return $this->isIndexableEnvironment() && $eligible && collect(array_keys($this->request->query()))->reject(fn ($key) => $key === 'page')->isEmpty() ? 'index,follow' : 'noindex,follow';
    }

    private function isIndexableEnvironment(): bool
    {
        return app()->environment('production') && (bool) config('seo.indexing_enabled', false) && (bool) (SeoSetting::query()->first()?->indexing_enabled ?? false);
    }
}
