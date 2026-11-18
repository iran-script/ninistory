<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageCategorySlot;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\KidokiDemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KidokiDemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('demo_catalog.public_preview', true);
        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_catalog_plan_contains_complete_taxonomy_and_forty_products(): void
    {
        $this->assertSame([
            'categories' => 78,
            'products' => 40,
            'variants' => 360,
            'homepage_slots' => 4,
        ], app(KidokiDemoCatalogSeeder::class)->plan());
    }

    public function test_seeding_is_idempotent_zero_stock_and_uses_labeled_placeholder_assets(): void
    {
        $seeder = app(KidokiDemoCatalogSeeder::class);
        $seeder->seedCatalog();
        $seeder->seedCatalog();

        $this->assertSame(78, Category::query()->where('slug', 'like', 'demo-kidoki-%')->count());
        $this->assertSame(40, Product::query()->where('slug', 'like', 'demo-kidoki-product-%')->count());
        $this->assertSame(360, ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->count());
        $this->assertSame(0, ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->sum('stock_quantity'));
        $this->assertSame(0, ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->sum('reserved_quantity'));
        $this->assertSame(0, ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->whereHas('inventoryMovements')->count());
        Storage::disk('public')->assertExists(KidokiDemoCatalogSeeder::IMAGE_PATH);
        $this->assertSame(4, HomepageCategorySlot::query()->count());
    }

    public function test_demo_products_render_in_public_preview_but_are_never_purchaseable(): void
    {
        app(KidokiDemoCatalogSeeder::class)->seedCatalog();
        config(['app.env' => 'production']);
        app()->detectEnvironment(fn (): string => 'production');

        $product = Product::query()->where('slug', 'demo-kidoki-product-001')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->get('/products')->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->name)->assertSee('محصول واقعی نیست');
        app()->detectEnvironment(fn (): string => 'testing');
        $this->postJson(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
        $this->assertSame([], session('cart', []));

        $this->withSession(['cart' => [$variant->id => 1]])->postJson(route('checkout.store'), ['phone' => '09120000000'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rollback_only_removes_unreferenced_namespaced_demo_records(): void
    {
        $seeder = app(KidokiDemoCatalogSeeder::class);
        $seeder->seedCatalog();
        $realCategory = Category::query()->create(['name' => 'دسته واقعی', 'slug' => 'real-category', 'is_active' => true]);
        Product::query()->create(['category_id' => $realCategory->id, 'name' => 'کالای واقعی', 'slug' => 'real-product', 'is_active' => true]);

        $result = $seeder->rollbackCatalog();

        $this->assertSame(40, $result['deleted_products']);
        $this->assertSame(78, $result['deleted_categories']);
        $this->assertDatabaseHas('products', ['slug' => 'real-product']);
        $this->assertDatabaseCount('products', 1);
        Storage::disk('public')->assertMissing(KidokiDemoCatalogSeeder::IMAGE_PATH);
    }
}
