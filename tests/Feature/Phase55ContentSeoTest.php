<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\SeoAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase55ContentSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_content_and_faq_fields_are_editable_by_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Girls', 'slug' => 'girls', 'sort_order' => 1, 'is_active' => 1,
            'primary_search_intent' => 'لباس دخترانه بچگانه', 'buying_guide' => 'راهنمای خرید تأییدشده',
            'faqs' => json_encode([['question' => 'سؤال واقعی', 'answer' => 'پاسخ واقعی']], JSON_UNESCAPED_UNICODE),
            'editorial_review_status' => 'needs_review',
        ])->assertRedirect();

        $category->refresh();
        $this->assertSame('لباس دخترانه بچگانه', $category->primary_search_intent);
        $this->assertSame('سؤال واقعی', $category->faqs[0]['question']);
        $this->assertSame('needs_review', $category->editorial_review_status);
    }

    public function test_unapproved_article_cannot_be_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title' => 'راهنمای سایز', 'slug' => 'size-guide', 'content' => 'محتوای بررسی‌شده',
            'status' => 'published', 'editorial_status' => 'needs_review',
        ])->assertSessionHasErrors('editorial_status');

        $this->assertDatabaseMissing('articles', ['slug' => 'size-guide']);
    }

    public function test_published_size_guide_renders_as_server_side_rtl_table(): void
    {
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);
        $category->sizeGuides()->create(['title' => 'راهنمای اندازه', 'rows' => [['سایز' => '۳ سال', 'قد' => '۹۸']], 'is_published' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Dress', 'slug' => 'dress', 'is_active' => true]);
        $product->variants()->create(['sku' => 'DRESS-1', 'size' => '3Y', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 2, 'is_active' => true]);

        $this->get(route('categories.show', $category))->assertOk()->assertSee('راهنمای اندازه')->assertSee('۳ سال');
    }

    public function test_audit_reports_real_content_defects_without_predictive_score(): void
    {
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty', 'is_active' => true, 'sort_order' => 1]);
        $findings = app(SeoAuditService::class)->findings();

        $this->assertTrue($findings->contains(fn (array $finding): bool => $finding['type'] === 'empty_category' && $finding['subject'] === $category->name));
        $this->assertFalse($findings->contains(fn (array $finding): bool => array_key_exists('score', $finding)));
    }

    public function test_demo_or_local_environment_remains_non_indexable(): void
    {
        config(['seo.indexing_enabled' => true]);
        $this->get(route('home'))->assertSee('noindex', false);
        $this->get(route('seo.robots'))->assertSee('Disallow: /');
    }
}
