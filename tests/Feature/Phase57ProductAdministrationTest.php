<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\ProductVariantSkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Phase57ProductAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_sku_is_generated_and_remains_stable_when_labels_change(): void
    {
        $product = Product::create(['name' => 'ست پاپیون دخترانه', 'slug' => 'bow-set', 'is_active' => false]);
        $variant = app(ProductVariantSkuService::class)->create($product, ['color_name' => 'صورتی', 'size' => '۴۵', 'price_toman' => 1000, 'stock_quantity' => 0, 'is_active' => true]);
        $sku = $variant->sku;
        $variant->update(['color_name' => 'Pink', 'size' => '50']);

        $this->assertStringStartsWith('KD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT), $sku);
        $this->assertSame($sku, $variant->fresh()->sku);
    }

    public function test_duplicate_color_and_size_are_rejected_by_product_editor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Set', 'slug' => 'set', 'is_active' => false]);
        $payload = ['name' => 'Set', 'slug' => 'set', 'category_id' => $category->id, 'variants' => ['new-a' => ['color_name' => 'Pink', 'size' => '45', 'price_toman' => 1000, 'is_active' => 1], 'new-b' => ['color_name' => 'Pink', 'size' => '45', 'price_toman' => 1000, 'is_active' => 1]]];

        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('variants');
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_variant_with_inventory_history_is_archived_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Set', 'slug' => 'set', 'is_active' => false]);
        $variant = $product->variants()->create(['sku' => 'KD-000001-PK-45', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 1, 'is_active' => true]);
        $variant->inventoryMovements()->create(['user_id' => $admin->id, 'quantity_delta' => 1, 'quantity_before' => 0, 'quantity_after' => 1, 'reason' => 'receipt', 'movement_type' => 'receipt', 'physical_before' => 0, 'physical_after' => 1, 'reserved_before' => 0, 'reserved_after' => 0]);

        $this->actingAs($admin)->delete(route('admin.products.variants.destroy', [$product, $variant]))->assertRedirect();
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'is_active' => 0]);
    }

    public function test_draft_preview_requires_admin_and_bulk_pricing_only_changes_selected_variants(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Preview set', 'slug' => 'preview-set', 'is_active' => false]);
        $first = $product->variants()->create(['sku' => 'PREVIEW-1', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 3, 'is_active' => true]);
        $second = $product->variants()->create(['sku' => 'PREVIEW-2', 'size' => '50', 'color_name' => 'Pink', 'price_toman' => 2000, 'stock_quantity' => 4, 'is_active' => true]);

        $this->get(route('admin.products.preview', $product))->assertRedirect(route('admin.login'));
        $this->actingAs($admin)->get(route('admin.products.preview', $product))->assertOk()->assertSee('Preview set')->assertSee('noindex');
        $this->actingAs($admin)->post(route('admin.products.prices.preview', $product), ['variant_ids' => [$first->id], 'price_toman' => 1500])->assertOk()->assertSee('PREVIEW-1')->assertDontSee('PREVIEW-2');
        $this->actingAs($admin)->post(route('admin.products.prices.apply', $product), ['variant_ids' => [$first->id], 'price_toman' => 1500])->assertRedirect();
        $this->assertSame(1500, $first->fresh()->price_toman);
        $this->assertSame(2000, $second->fresh()->price_toman);
        $this->assertSame(3, $first->fresh()->stock_quantity);
    }

    public function test_product_image_upload_and_cover_selection_are_isolated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Gallery set', 'slug' => 'gallery-set', 'is_active' => false]);
        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Gallery set', 'slug' => 'gallery-set', 'images' => [UploadedFile::fake()->image('one.jpg', 1200, 1200), UploadedFile::fake()->image('two.jpg', 1200, 1200)]]);
        $response->assertRedirect();
        $this->assertDatabaseCount('product_images', 2);
        $images = $product->fresh()->images;
        $this->actingAs($admin)->post(route('admin.products.images.cover', [$product, $images->last()]))->assertRedirect();
        $this->assertTrue((bool) $images->last()->fresh()->is_cover);
        $this->assertFalse((bool) $images->first()->fresh()->is_cover);
    }

    public function test_isolated_merchant_flow_reaches_mock_payment_and_commits_reservation(): void
    {
        Config::set(['payment.mock_enabled' => true, 'payment.gateway' => 'mock']);
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.products.store'), ['name' => 'ست پاپیون دخترانه', 'slug' => 'bow-merchant-flow', 'category_id' => $category->id, 'description' => 'توضیح تأییدشده', 'measured_dimensions' => json_encode([['size' => '45', 'shirt_length' => '38.5']], JSON_UNESCAPED_UNICODE), 'images' => [UploadedFile::fake()->image('merchant.jpg', 1200, 1200)], 'variants' => ['new' => ['color_name' => 'صورتی', 'size' => '۴۵', 'price_toman' => 150000, 'is_active' => 1]]])->assertRedirect();
        $product = Product::where('slug', 'bow-merchant-flow')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertStringStartsWith('KD-', $variant->sku);
        $this->actingAs($admin)->patch(route('admin.variants.stock', $variant), ['quantity' => 5, 'reason' => 'دریافت اولیه'])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => $product->name, 'slug' => $product->slug, 'category_id' => $category->id, 'description' => $product->description, 'measured_dimensions' => json_encode([['size' => '45', 'shirt_length' => '38.5']], JSON_UNESCAPED_UNICODE), 'is_active' => 1, 'variants' => [$variant->id => ['sku' => $variant->sku, 'color_name' => $variant->color_name, 'size' => $variant->size, 'price_toman' => 150000, 'is_active' => 1]]])->assertRedirect();
        $this->actingAs($admin)->get(route('admin.products.preview', $product))->assertOk()->assertSee('ست پاپیون دخترانه');
        $this->get(route('products.show', $product->fresh()))->assertOk()->assertSee('ست پاپیون دخترانه');
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        ShippingMethod::updateOrCreate(['code' => 'pickup'], ['name' => 'تحویل حضوری', 'is_enabled' => true, 'sort_order' => 1, 'base_fee_toman' => 0, 'requires_address' => false, 'free_shipping_eligible' => true]);
        $pickup = PickupLocation::create(['code' => 'main', 'name' => 'فروشگاه کیدوکی', 'province_code' => 'tehran', 'city_code' => 'tehran', 'address' => 'نشانی تأییدشده', 'is_enabled' => true, 'sort_order' => 1]);
        $this->withSession(['cart' => [$variant->id => 1]])->post(route('checkout.store'), ['phone' => '09120000000', 'shipping_method' => 'pickup', 'pickup_location_id' => $pickup->id])->assertRedirect();
        $order = Order::latest('id')->firstOrFail();
        $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order))->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->post(URL::temporarySignedRoute('payments.mock.action', now()->addMinutes(5), ['payment' => $payment]), ['action' => 'success'])->assertRedirect();
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(StockReservation::COMMITTED, $order->reservations()->firstOrFail()->status);
    }
}
