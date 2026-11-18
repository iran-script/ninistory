<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase60ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_normalization_handles_persian_arabic_digits_spacing_and_punctuation(): void
    {
        $normalized = app(ProductSearchService::class)->normalize("  \u{064A}\u{0643}  \u{06F1}\u{06F2}\u{200C}  لباس! ");

        $this->assertSame("\u{06CC}\u{06A9} 12 لباس", $normalized);
    }

    public function test_autocomplete_returns_ranked_results_with_product_data(): void
    {
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls-search', 'is_active' => true]);
        $exact = $this->product('Dress', 'dress-exact', $category, 'SKU-EXACT', 3);
        $prefix = $this->product('Dress for summer', 'dress-prefix', $category, 'SKU-PREFIX', 0);
        $categoryMatch = $this->product('Summer set', 'set-category', Category::create(['name' => 'Dress collection', 'slug' => 'dress-collection', 'is_active' => true]), 'SKU-SET', 2);

        $response = $this->getJson(route('search.autocomplete', ['q' => 'Dress']));

        $response->assertOk()->assertJsonStructure(['query', 'results' => [['id', 'name', 'category', 'thumbnail', 'price_toman', 'price_label', 'available', 'availability_label', 'url']], 'all_results_url']);
        $this->assertSame($exact->id, $response->json('results.0.id'));
        $this->assertSame($prefix->id, $response->json('results.1.id'));
        $this->assertContains($categoryMatch->id, collect($this->getJson(route('search.autocomplete', ['q' => 'Dress collection']))->json('results'))->pluck('id')->all());
    }

    public function test_search_autocomplete_returns_visible_results_for_boolean_columns(): void
    {
        $category = Category::create(['name' => 'Shirts', 'slug' => 'shirts-search', 'is_active' => true]);
        $visible = $this->product('Summer shirt', 'summer-shirt', $category, 'SHIRT-1', 2);
        $hidden = $this->product('Hidden shirt', 'hidden-shirt', $category, 'SHIRT-2', 2, false);
        $visible->variants()->create(['sku' => 'SHIRT-3', 'size' => '4Y', 'color_name' => 'Blue', 'price_toman' => 125000, 'stock_quantity' => 2, 'is_active' => false]);

        $response = $this->getJson(route('search.autocomplete', ['q' => 'shirt']));

        $response->assertOk()->assertJsonStructure(['query', 'results', 'all_results_url']);
        $this->assertContains($visible->id, collect($response->json('results'))->pluck('id')->all());
        $this->assertNotContains($hidden->id, collect($response->json('results'))->pluck('id')->all());
    }

    public function test_sku_and_category_search_share_the_same_endpoint(): void
    {
        $category = Category::create(['name' => 'Winter jackets', 'slug' => 'winter-search', 'is_active' => true]);
        $product = $this->product('Warm coat', 'warm-coat', $category, 'KD-0005-BL-45', 1);

        $this->getJson(route('search.autocomplete', ['q' => '0005']))->assertJsonPath('results.0.id', $product->id);
        $this->getJson(route('search.autocomplete', ['q' => 'Winter']))->assertJsonPath('results.0.id', $product->id);
    }

    public function test_multiword_search_and_persian_size_filter_find_the_same_product(): void
    {
        $category = Category::create(['name' => 'پسرانه', 'slug' => 'boys-multiword', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'ست پسرانه تابستانی', 'slug' => 'boys-set-multiword', 'is_active' => true]);
        $product->variants()->create(['sku' => 'BOYS-45', 'size' => '45', 'color_name' => 'آبی', 'price_toman' => 900000, 'stock_quantity' => 3, 'is_active' => true]);

        $this->get(route('products.index', ['q' => 'ست پسرانه']))->assertOk()->assertSee('ست پسرانه تابستانی');
        $this->get(route('products.index', ['size' => ['۴۵']]))->assertOk()->assertSee('ست پسرانه تابستانی');
    }

    public function test_size_and_color_filters_use_one_real_variant_combination(): void
    {
        $category = Category::create(['name' => 'ترکیب‌ها', 'slug' => 'combination-filter', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'ترکیب واقعی', 'slug' => 'real-combination', 'is_active' => true]);
        $product->variants()->create(['sku' => 'COMBO-PINK-45', 'size' => '45', 'color_name' => 'صورتی', 'price_toman' => 1000, 'stock_quantity' => 2, 'is_active' => true]);
        $product->variants()->create(['sku' => 'COMBO-BLUE-50', 'size' => '50', 'color_name' => 'آبی', 'price_toman' => 1000, 'stock_quantity' => 2, 'is_active' => true]);

        $this->get(route('products.index', ['size' => ['45'], 'color' => ['آبی']]))->assertOk()->assertDontSee('ترکیب واقعی');
        $this->get(route('products.index', ['size' => ['45'], 'color' => ['صورتی'], 'available' => 1]))->assertOk()->assertSee('ترکیب واقعی');
    }

    public function test_size_filter_normalizes_digits_and_trims_stored_values_while_preserving_selection(): void
    {
        $category = Category::create(['name' => 'Size codes', 'slug' => 'size-codes', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Trimmed size product', 'slug' => 'trimmed-size-product', 'is_active' => true]);
        $product->variants()->create(['sku' => 'SIZE-45-TRIM', 'size' => ' ۴۵ ', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 2, 'is_active' => true]);

        $response = $this->get(route('products.index', ['size' => ['45']]));

        $response->assertOk()->assertSee('Trimmed size product')->assertSee('name="size[]" value="۴۵" checked', false);
        $this->get(route('products.index', ['size' => ['٤٥']]))->assertSee('Trimmed size product');
    }

    public function test_catalog_filters_preserve_url_state_and_offer_mobile_sheet_controls(): void
    {
        $category = Category::create(['name' => 'Filter category', 'slug' => 'filter-category', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Filter product', 'slug' => 'filter-product', 'is_active' => true]);
        $product->variants()->create(['sku' => 'FILTER-45', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'is_active' => true]);

        $response = $this->get(route('products.index', ['size' => ['45'], 'color' => ['Pink']]));

        $response->assertOk()->assertSee('data-filter-open', false)->assertSee('data-filter-close', false)->assertSee('name="size[]" value="45" checked', false)->assertSee('name="color[]" value="Pink" checked', false);
    }

    public function test_hidden_inactive_archived_and_production_demo_products_are_not_returned(): void
    {
        $category = Category::create(['name' => 'Catalog', 'slug' => 'catalog-search', 'is_active' => true]);
        $visible = $this->product('Visible demo term', 'real-search', $category, 'REAL-1', 1);
        $this->product('Hidden demo term', 'hidden-search', $category, 'HIDDEN-1', 1, false);
        $archived = $this->product('Archived demo term', 'archived-search', $category, 'ARCHIVED-1', 1);
        $archived->variants()->update(['archived_at' => now()]);
        $demo = $this->product('Demo demo term', 'demo-search', $category, 'DEMO-1', 1);

        $this->getJson(route('search.autocomplete', ['q' => 'demo']))->assertJsonPath('results.0.id', $demo->id);
        $this->app->detectEnvironment(fn (): string => 'production');
        $response = $this->getJson(route('search.autocomplete', ['q' => 'demo']));
        $this->assertNotContains($demo->id, collect($response->json('results'))->pluck('id')->all());
        $this->assertNotContains($visible->id, collect($this->getJson(route('search.autocomplete', ['q' => 'Visible']))->json('results'))->pluck('id')->all());
    }

    public function test_short_and_oversized_queries_are_bounded(): void
    {
        $this->getJson(route('search.autocomplete', ['q' => 'a']))->assertOk()->assertJsonCount(0, 'results');
        $this->getJson(route('search.autocomplete', ['q' => str_repeat('a', 81)]))->assertUnprocessable();
    }

    private function product(string $name, string $slug, Category $category, string $sku, int $stock, bool $active = true): Product
    {
        $product = Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'is_active' => $active]);
        $product->variants()->create(['sku' => $sku, 'size' => '45', 'color_name' => 'Blue', 'price_toman' => 125000, 'stock_quantity' => $stock, 'reserved_quantity' => 0, 'is_active' => true]);

        return $product;
    }
}
