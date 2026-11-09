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

    public function test_category_hierarchy_rejects_cycles(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'is_active' => true, 'sort_order' => 1]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true, 'sort_order' => 1]);

        $this->put(route('admin.categories.update', $parent), [
            'name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $child->id,
            'sort_order' => 1, 'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_non_admin_cannot_adjust_inventory_or_delete_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $product = Product::create(['name' => 'Protected', 'slug' => 'protected', 'is_active' => false]);
        $variant = $product->variants()->create(['sku' => 'PROTECTED-1', 'size' => '3Y', 'color_name' => 'Blue', 'price_toman' => 1000]);
        $image = $product->images()->create(['path' => 'products/1/a.jpg', 'alt_text' => 'a', 'sort_order' => 0, 'is_cover' => true]);
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->patch(route('admin.variants.stock', $variant), ['quantity' => 8, 'reason' => 'unauthorized'])->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.products.images.destroy', [$product, $image]))->assertForbidden();
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_published_product_requires_an_active_variant(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post(route('admin.products.store'), [
            'name' => 'Draft variant', 'slug' => 'draft-variant', 'is_active' => 1,
            'variants' => ['new-1' => ['sku' => 'DRAFT-1', 'size' => '3Y', 'color_name' => 'Red', 'price_toman' => 1000]],
        ])->assertSessionHasErrors('variants');
        $this->assertDatabaseMissing('products', ['slug' => 'draft-variant']);
    }

    public function test_admin_product_create_form_renders(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="variants[0][sku]"', false)
            ->assertSee('multipart/form-data', false);
    }

    public function test_admin_product_edit_form_renders(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Editable', 'slug' => 'editable', 'is_active' => false]);
        $product->variants()->create([
            'sku' => 'EDITABLE-1', 'size' => '3Y', 'color_name' => 'Blue', 'price_toman' => 1000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('EDITABLE-1')
            ->assertSee('name="quantity"', false);
    }
}
