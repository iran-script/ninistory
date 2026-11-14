<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoRedirect;
use App\Models\SeoSetting;
use App\Services\SeoSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_unique_metadata_and_staging_noindex(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('<meta name="description"', false)->assertSee('<link rel="canonical"', false)->assertSee('noindex,follow', false);
    }

    public function test_product_json_ld_uses_real_variant_price_in_rials_without_fake_reviews(): void
    {
        $this->seed();
        $product = Product::where('is_active', true)->firstOrFail();
        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
        $schema = json_decode($match[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame(8900000, $schema['offers']['price']);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertArrayNotHasKey('review', $schema);
    }

    public function test_sitemap_contains_published_products_but_not_unpublished_products(): void
    {
        $this->seed();
        $hidden = Product::create(['name' => 'Hidden', 'slug' => 'hidden-seo-product', 'is_active' => false]);
        $xml = $this->get(route('seo.sitemap.products'))->assertOk()->getContent();
        $this->assertStringContainsString('/products/test-girls', $xml);
        $this->assertStringNotContainsString($hidden->slug, $xml);
    }

    public function test_local_robots_disallow_crawling_and_category_has_canonical_url(): void
    {
        $this->seed();
        $this->get(route('seo.robots'))->assertOk()->assertSee('Disallow: /');
        $category = Category::where('slug', 'girls')->firstOrFail();
        $this->get(route('categories.show', $category))->assertOk()->assertSee(route('categories.show', $category), false);
    }

    public function test_indexing_requires_production_config_and_explicit_admin_setting(): void
    {
        config(['seo.indexing_enabled' => true]);
        SeoSetting::create(['indexing_enabled' => true]);
        $this->get(route('home'))->assertSee('noindex,follow', false);
    }

    public function test_toman_conversion_is_explicit(): void
    {
        $this->assertSame(1250000, app(SeoSchemaService::class)->rials(125000));
    }

    public function test_draft_article_is_not_public_or_in_sitemap(): void
    {
        $article = Article::create(['title' => 'Draft', 'slug' => 'draft', 'content' => '<script>alert(1)</script>', 'status' => 'draft']);
        $this->get(route('articles.show', $article))->assertNotFound();
        $this->get(route('seo.sitemap.articles'))->assertOk()->assertDontSee('/articles/draft');
    }

    public function test_explicit_slug_history_redirects_permanently_without_redirecting_unknown_paths(): void
    {
        SeoRedirect::create(['source_path' => 'products/old-dress', 'target_url' => route('products.index'), 'status_code' => 301]);
        $this->get('/products/old-dress')->assertRedirect(route('products.index'))->assertStatus(301);
        $this->get('/products/never-existed')->assertNotFound();
    }

    public function test_seo_admin_requires_admin_authorization(): void
    {
        $this->get(route('admin.seo.edit'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.articles.index'))->assertRedirect(route('admin.login'));
    }
}
