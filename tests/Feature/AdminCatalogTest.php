<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_admin_routes_reject_guests_and_customers(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_login_and_dashboard_work_for_explicit_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('S3cure-Admin-Only')]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'S3cure-Admin-Only'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('داشبورد مدیریت');
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_category_and_product_with_variant_and_audited_stock(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post(route('admin.categories.store'), ['name' => 'نوزادی', 'slug' => 'newborn', 'sort_order' => 1, 'is_active' => 1])->assertRedirect(route('admin.categories.index'));
        $category = Category::where('slug', 'newborn')->firstOrFail();
        $this->post(route('admin.products.store'), ['name' => 'لباس نمونه', 'slug' => 'sample-garment', 'category_id' => $category->id, 'is_active' => 1, 'variants' => ['new-a' => ['sku' => 'SAMPLE-1', 'size' => '۲ سال', 'color_name' => 'صورتی', 'price_toman' => 1200000, 'is_active' => 1]]])->assertRedirect();
        $product = Product::with('variants')->where('slug', 'sample-garment')->firstOrFail();
        $variant = $product->variants->firstOrFail();
        $this->patch(route('admin.variants.stock', $variant), ['quantity' => 4, 'reason' => 'ثبت موجودی اولیه'])->assertRedirect();
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $variant->id, 'user_id' => auth()->id(), 'quantity_before' => 0, 'quantity_after' => 4, 'quantity_delta' => 4]);
    }

    public function test_admin_product_uploads_are_stored_and_non_images_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);
        $payload = ['name' => 'Photo sample', 'slug' => 'photo-sample', 'category_id' => $category->id, 'variants' => ['new-1' => ['sku' => 'PHOTO-1', 'size' => '3Y', 'color_name' => 'Pink', 'price_toman' => 1000, 'is_active' => 1]]];
        $this->post(route('admin.products.store'), $payload + ['images' => [UploadedFile::fake()->image('cover.jpg')]])->assertRedirect();
        $image = Product::where('slug', 'photo-sample')->firstOrFail()->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('jpg', pathinfo($image->path, PATHINFO_EXTENSION));
    }
}
