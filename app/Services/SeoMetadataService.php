<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoMetadataService
{
    public function __construct(private Request $request) {}

    public function defaults(): array
    {
        $settings = $this->settings();

        return $this->make(
            $settings?->default_title ?: 'کیدوکی | خرید لباس کودک و نوزاد',
            $settings?->default_description ?: 'کیدوکی؛ فروشگاه اینترنتی لباس کودک و نوزاد با انتخاب‌های لطیف و دوست‌داشتنی برای کوچولوها.',
            url('/'),
            $this->isIndexableEnvironment() ? 'index,follow' : 'noindex,nofollow'
        );
    }

    public function home(): array
    {
        $settings = $this->settings();

        return $this->make($settings?->default_title ?: 'خرید لباس بچگانه | کیدوکی', $settings?->default_description ?: 'خرید لباس بچگانه، لباس دخترانه، پسرانه و نوزادی از کیدوکی؛ قصه‌های قشنگ کوچولوها.', url('/'), $this->indexingDirective());
    }

    public function product(Product $product): array
    {
        $description = $product->meta_description ?: Str::limit(strip_tags($product->short_description ?: $product->description ?: 'خرید '.$product->name.' از کیدوکی.'), 155);

        return $this->make($product->meta_title ?: $product->name.' | خرید لباس کودک | کیدوکی', $description, $product->canonical_url ?: route('products.show', $product), $product->robots_directive ?: $this->indexingDirective($product->is_active));
    }

    public function category(Category $category, bool $hasProducts = true): array
    {
        $description = $category->meta_description ?: Str::limit(strip_tags($category->description ?: 'خرید '.$category->name.' از فروشگاه لباس کودک کیدوکی.'), 155);

        return $this->make($category->meta_title ?: 'خرید '.$category->name.' | کیدوکی', $description, $category->canonical_url ?: route('categories.show', $category), $category->robots_directive ?: $this->indexingDirective($category->is_active && $hasProducts));
    }

    public function article(Article $article): array
    {
        return $this->make($article->meta_title ?: $article->title.' | مجله کیدوکی', Str::limit(strip_tags($article->meta_description ?: $article->summary ?: $article->content), 155), $article->canonical_url ?: route('articles.show', $article), $article->robots_directive ?: $this->indexingDirective($article->status === 'published'));
    }

    public function make(string $title, string $description, string $canonical, string $robots = 'index,follow', ?string $image = null): array
    {
        return ['title' => Str::limit(trim($title), 65, ''), 'description' => Str::limit(trim(strip_tags($description)), 160, ''), 'canonical' => $this->canonicalUrl($canonical), 'robots' => $robots, 'og_title' => trim($title), 'og_description' => Str::limit(trim(strip_tags($description)), 200, ''), 'og_image' => $image ?: $this->brandLogoUrl()];
    }

    private function brandLogoUrl(): string
    {
        return app()->environment('production') ? config('seo.production_url').'/brand-logo.webp' : asset('brand-logo.webp');
    }

    private function canonicalUrl(string $url): string
    {
        if (! app()->environment('production')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return config('seo.production_url').$path.($query ? '?'.$query : '');
    }

    private function indexingDirective(bool $eligible = true): string
    {
        return $this->isIndexableEnvironment() && $eligible && collect(array_keys($this->request->query()))->reject(fn ($key) => $key === 'page')->isEmpty() ? 'index,follow' : 'noindex,follow';
    }

    private function isIndexableEnvironment(): bool
    {
        return app()->environment('production') && (bool) config('seo.indexing_enabled', false) && (bool) ($this->settings()?->indexing_enabled ?? false);
    }

    public function settings(): ?SeoSetting
    {
        $attributes = Cache::remember(SeoSetting::CACHE_KEY, now()->addMinutes(10), fn (): ?array => SeoSetting::query()->first()?->getAttributes());

        return $attributes ? SeoSetting::query()->getModel()->newFromBuilder($attributes) : null;
    }
}
