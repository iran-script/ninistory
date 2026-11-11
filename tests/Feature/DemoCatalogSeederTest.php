<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('demo_catalog.enabled', true);
        $this->withoutVite();
    }

    public function test_category_only_partial_seed_is_recovered_atomically(): void
    {
        Category::create(['name' => 'Existing category', 'slug' => 'demo-girls', 'is_active' => true]);

        $this->seed(CatalogDemoSeeder::class);

        $this->assertSame(12, Category::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(40, Product::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(120, ProductVariant::where('sku', 'like', 'DEMO-%')->count());
    }

    public function test_demo_seed_is_idempotent_and_preserves_real_products(): void
    {
        $category = Category::create(['name' => 'Real category', 'slug' => 'real-category', 'is_active' => true]);
        $real = Product::create(['category_id' => $category->id, 'name' => 'Real product', 'slug' => 'real-product', 'is_active' => false]);
        $realVariant = $real->variants()->create(['sku' => 'REAL-1', 'size' => '3Y', 'color_name' => 'Blue', 'price_toman' => 1000, 'stock_quantity' => 4]);

        $this->seed(CatalogDemoSeeder::class);
        $this->seed(CatalogDemoSeeder::class);

        $this->assertSame(12, Category::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(40, Product::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(120, ProductVariant::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(['name' => 'Real product', 'is_active' => false], $real->fresh()->only(['name', 'is_active']));
        $this->assertSame(4, $realVariant->fresh()->stock_quantity);
    }

    public function test_demo_products_are_published_visible_and_inventory_is_consistent(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->assertSame(40, Product::where('slug', 'like', 'demo-%')->where('is_active', true)->count());
        $this->assertSame(120, ProductVariant::where('sku', 'like', 'DEMO-%')->where('is_active', true)->count());
        $this->assertSame(115, ProductVariant::where('sku', 'like', 'DEMO-%')->where('stock_quantity', '>', 0)->count());
        $this->assertSame(5, ProductVariant::where('sku', 'like', 'DEMO-%')->where('stock_quantity', 0)->count());
        $this->assertSame(115, ProductVariant::where('sku', 'like', 'DEMO-%')->whereHas('inventoryMovements')->count());

        $product = Product::where('slug', 'demo-girls-01')->firstOrFail();
        $this->get(route('home'))->assertOk()->assertSee('class="product-card"', false);
        $this->get(route('products.index', ['q' => $product->name]))->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->name);
        $this->get(route('products.index', ['q' => $product->name, 'available' => 1]))->assertOk()->assertSee($product->name);
        $this->get(route('home'))->assertDontSee('/_local-preview/', false);

        $variant = ProductVariant::where('sku', 'DEMO-GIRLS-01-2')->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect(route('cart'));
    }

    public function test_admin_catalog_lists_demo_products(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('DEMO-');
    }
}
