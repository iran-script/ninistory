<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_persian_homepage_and_listing_render_utf8(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('نینی‌استوری');
        $this->get('/products?q=سرهمی')->assertOk()->assertSee('سرهمی');
    }

    public function test_cart_rejects_quantity_above_stock_and_accepts_valid_variant(): void
    {
        $this->seed();
        $variant = Product::firstOrFail()->variants()->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 9])->assertRedirect();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 9])->assertSessionHasErrors('variant_id');
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertOk()->assertSee('1,780,000');
    }

    public function test_guest_checkout_creates_pending_order_and_reserves_stock_without_deducting_physical_stock(): void
    {
        $this->seed();
        $variant = Product::firstOrFail()->variants()->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post(route('checkout.store'), ['recipient_name' => 'آزمایش', 'phone' => '09120000000', 'address' => 'تهران', 'postal_code' => '1234567890'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['subtotal_toman' => 1780000, 'status' => 'pending_payment', 'currency' => 'IRR']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(8, $variant->fresh()->stock_quantity);
        $this->assertSame(2, $variant->fresh()->reserved_quantity);
        $this->assertDatabaseHas('stock_reservations', ['quantity' => 2, 'status' => 'active']);
    }

    public function test_missing_local_demo_assets_do_not_emit_broken_preview_urls(): void
    {
        $this->seed();

        $this->get('/')->assertOk()->assertDontSee('/_local-preview/images/', false);
        $this->get(route('products.show', Product::firstOrFail()))
            ->assertOk()
            ->assertSee('product-media__fallback', false);
    }

    public function test_existing_product_image_uses_the_public_storage_url(): void
    {
        Storage::fake('public');
        $product = Product::create(['name' => 'Visible product', 'slug' => 'visible-product', 'is_active' => true]);
        $product->variants()->create([
            'sku' => 'VISIBLE-1', 'size' => '3Y', 'color_name' => 'Pink', 'price_toman' => 1000,
            'stock_quantity' => 2, 'is_active' => true,
        ]);
        $imagePath = 'products/'.$product->id.'/cover.jpg';
        Storage::disk('public')->put($imagePath, 'image');
        $product->images()->create(['path' => $imagePath, 'alt_text' => 'Cover', 'sort_order' => 0, 'is_cover' => true]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('/storage/'.$imagePath, false);
    }

    public function test_homepage_uses_active_managed_banner_media(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/managed.jpg', 'image');
        Banner::create([
            'title' => 'Managed hero', 'subtitle' => 'Managed subtitle',
            'desktop_image_path' => 'banners/managed.jpg', 'alt_text' => 'Managed hero image',
            'position' => 'hero', 'sort_order' => 0, 'is_active' => true, 'cta_url' => '/products',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('/storage/banners/managed.jpg', false)
            ->assertSee('Managed hero');
    }
}
