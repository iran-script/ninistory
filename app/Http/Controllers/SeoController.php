<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $body = $this->indexingEnabled()
            ? "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /payments\nDisallow: /account\nDisallow: /orders\nSitemap: ".(app()->environment('production') ? config('seo.production_url') : url('/'))."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function index(): Response
    {
        $xml = Cache::remember('seo:sitemap:index', now()->addHours(6), fn () => $this->urlSet([
            ['loc' => url('/sitemap-static.xml')],
            ['loc' => url('/sitemap-products.xml')],
            ['loc' => url('/sitemap-categories.xml')],
            ['loc' => url('/sitemap-articles.xml')],
        ], true));

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function products(): Response
    {
        $xml = Cache::remember('seo:sitemap:products', now()->addSeconds(config('performance.public_cache_ttl', 300)), function (): string {
            $products = Product::query()->where('is_active', true)->where('slug', 'not like', config('demo_catalog.prefix').'%')->when(app()->environment('production'), fn ($query) => $query->where('merchant_verification_status', 'approved')->where('content_review_status', 'approved'))->whereHas('variants', fn ($query) => $query->where('is_active', true))->select(['id', 'slug', 'updated_at'])->orderBy('id')->get();

            return $this->urlSet($products->map(fn ($product) => ['loc' => route('products.show', $product), 'lastmod' => $product->updated_at?->toAtomString()])->all());
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function categories(): Response
    {
        $xml = Cache::remember('seo:sitemap:categories', now()->addSeconds(config('performance.public_cache_ttl', 300)), function (): string {
            $categories = Category::query()->where('is_active', true)->whereHas('products', fn ($query) => $query->where('is_active', true)->when(app()->environment('production'), fn ($nested) => $nested->where('merchant_verification_status', 'approved')->where('content_review_status', 'approved'))->whereHas('variants', fn ($variants) => $variants->where('is_active', true)))->select(['id', 'slug', 'updated_at'])->get();

            return $this->urlSet($categories->map(fn ($category) => ['loc' => route('categories.show', $category), 'lastmod' => $category->updated_at?->toAtomString()])->all());
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function articles(): Response
    {
        $xml = Cache::remember('seo:sitemap:articles', now()->addSeconds(config('performance.public_cache_ttl', 300)), function (): string {
            $articles = Article::published()->select(['id', 'slug', 'updated_at', 'published_at'])->get();

            return $this->urlSet($articles->map(fn ($article) => ['loc' => route('articles.show', $article), 'lastmod' => ($article->updated_at ?: $article->published_at)?->toAtomString()])->all());
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function static(): Response
    {
        return response($this->urlSet([['loc' => route('home')], ['loc' => route('products.index')], ['loc' => route('articles.index')]]))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function urlSet(array $urls, bool $index = false): string
    {
        $root = $index ? 'sitemapindex' : 'urlset';
        $namespace = $index ? 'sitemap' : 'urlset';
        $body = collect($urls)->map(function (array $url) use ($index): string {
            $tag = $index ? 'sitemap' : 'url';
            $lastmod = isset($url['lastmod']) ? '<lastmod>'.e($url['lastmod']).'</lastmod>' : '';

            return '<'.$tag.'><loc>'.e($url['loc']).'</loc>'.$lastmod.'</'.$tag.'>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</'.$root.'>';
    }

    private function indexingEnabled(): bool
    {
        return app()->environment('production') && config('seo.indexing_enabled') && (bool) (SeoSetting::query()->first()?->indexing_enabled ?? false);
    }
}
