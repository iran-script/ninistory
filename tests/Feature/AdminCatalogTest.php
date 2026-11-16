<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ImageVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->post(route('admin.products.store'), $payload + [
            'images' => [UploadedFile::fake()->image('cover.jpg', 1200, 1500)],
            'image_alt' => [0 => 'نمای روبه‌رو'],
            'image_caption' => [0 => 'نمای جلوی لباس'],
        ])->assertRedirect();
        $image = Product::where('slug', 'photo-sample')->firstOrFail()->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('jpg', pathinfo($image->path, PATHINFO_EXTENSION));
        $this->assertSame(1200, $image->width);
        $this->assertSame(1500, $image->height);
        $this->assertSame('نمای روبه‌رو', $image->alt_text);
        $this->assertSame('نمای جلوی لباس', $image->caption);
        Storage::disk('public')->assertExists(app(ImageVariantService::class)->variantPath($image->path, 400));
        Storage::disk('public')->assertExists(app(ImageVariantService::class)->variantPath($image->path, 800));
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
        $this->actingAs($customer)->post(route('admin.products.duplicate', $product))->assertForbidden();
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
            ->assertSee('name="save_as_draft"', false)
            ->assertSee('new-image-previews')
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

    public function test_admin_can_save_a_published_form_as_a_draft(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->post(route('admin.products.store'), [
            'name' => 'پیش‌نویس امن', 'slug' => 'safe-draft', 'is_active' => 1, 'save_as_draft' => 1,
            'variants' => ['new-1' => ['sku' => 'SAFE-DRAFT-1', 'size' => '۳ سال', 'color_name' => 'آبی', 'price_toman' => 1000, 'is_active' => 1]],
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['slug' => 'safe-draft', 'is_active' => false]);
    }

    public function test_product_duplication_copies_images_and_variants_without_copying_stock(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Original item', 'slug' => 'original-item', 'is_active' => true]);
        $variant = $product->variants()->create(['sku' => 'ORIGINAL-1', 'size' => '4Y', 'color_name' => 'Pink', 'price_toman' => 2500, 'stock_quantity' => 7]);
        Storage::disk('public')->put('products/'.$product->id.'/cover.jpg', 'image-bytes');
        $product->images()->create(['path' => 'products/'.$product->id.'/cover.jpg', 'alt_text' => 'Pink item', 'caption' => 'Front view', 'sort_order' => 0, 'is_cover' => true]);

        $response = $this->actingAs($admin)->post(route('admin.products.duplicate', $product));

        $copy = Product::query()->where('id', '!=', $product->id)->with(['variants', 'images'])->firstOrFail();
        $response->assertRedirect(route('admin.products.edit', $copy));
        $this->assertFalse($copy->is_active);
        $this->assertCount(1, $copy->variants);
        $this->assertSame(0, $copy->variants->first()->stock_quantity);
        $this->assertNotSame($variant->sku, $copy->variants->first()->sku);
        $this->assertCount(1, $copy->images);
        Storage::disk('public')->assertExists($copy->images->first()->path);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => true]);
    }

    public function test_complete_variant_matrix_preserves_ids_and_stock_and_archives_omitted_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Matrix item', 'slug' => 'matrix-item']);
        $keep = $product->variants()->create(['sku' => 'MATRIX-KEEP', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 5]);
        $excluded = $product->variants()->create(['sku' => 'MATRIX-EXCLUDE', 'size' => '50', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 2]);
        $excluded->inventoryMovements()->create(['user_id' => $admin->id, 'quantity_delta' => 2, 'quantity_before' => 0, 'quantity_after' => 2, 'reason' => 'Initial stock', 'movement_type' => 'adjustment', 'physical_before' => 0, 'physical_after' => 2, 'reserved_before' => 0, 'reserved_after' => 0]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Matrix item', 'slug' => 'matrix-item', 'variants_complete' => 1,
            'variants' => [$keep->id => ['sku' => 'MATRIX-KEEP', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1200, 'is_active' => 1]],
        ])->assertRedirect();

        $this->assertSame($keep->id, $product->variants()->where('sku', 'MATRIX-KEEP')->firstOrFail()->id);
        $this->assertSame(5, $keep->fresh()->stock_quantity);
        $this->assertDatabaseHas('product_variants', ['id' => $excluded->id, 'is_active' => false]);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $excluded->id]);
    }

    public function test_missing_or_invalid_variant_matrix_never_archives_existing_variants(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Safe item', 'slug' => 'safe-item']);
        $variant = $product->variants()->create(['sku' => 'SAFE-ROW', 'size' => '45', 'color_name' => 'Blue', 'price_toman' => 1000]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Safe item', 'slug' => 'safe-item', 'variants_complete' => 1, 'variants' => []])->assertSessionHasErrors('variants');
        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Safe item', 'slug' => 'safe-item', 'variants_complete' => 1, 'variants' => [$variant->id => ['color_name' => 'Blue']]])->assertSessionHasErrors('variants.'.$variant->id.'.size');
        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Safe item', 'slug' => 'safe-item', 'variants' => [$variant->id => ['color_name' => 'Blue', 'size' => '45', 'price_toman' => 1000]]])->assertSessionHasErrors('variants_complete');

        $this->assertTrue((bool) $variant->fresh()->is_active);
    }

    public function test_malformed_or_partial_matrix_preserves_product_and_variants(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Stable item', 'slug' => 'stable-item']);
        $variant = $product->variants()->create(['sku' => 'STABLE-ROW', 'size' => '45', 'color_name' => 'Blue', 'price_toman' => 1000]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Changed', 'slug' => 'stable-item', 'variants' => 'broken'])->assertSessionHasErrors('variants_complete');
        $this->assertSame('Stable item', $product->fresh()->name);
        $this->assertTrue((bool) $variant->fresh()->is_active);

        $this->put(route('admin.products.update', $product), ['name' => 'Basic edit', 'slug' => 'stable-item'])->assertRedirect();
        $this->assertSame('Basic edit', $product->fresh()->name);
        $this->assertTrue((bool) $variant->fresh()->is_active);
    }

    public function test_archiving_excluded_variant_keeps_active_reservation_and_inventory_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Reserved item', 'slug' => 'reserved-item']);
        $keep = $product->variants()->create(['sku' => 'RES-KEEP', 'size' => '45', 'color_name' => 'Blue', 'price_toman' => 1000]);
        $excluded = $product->variants()->create(['sku' => 'RES-OLD', 'size' => '50', 'color_name' => 'Blue', 'price_toman' => 1000, 'stock_quantity' => 3, 'reserved_quantity' => 1]);
        $order = Order::create(['number' => (string) Str::ulid(), 'checkout_key' => (string) Str::uuid(), 'recipient_name' => 'Test', 'phone' => '09120000000', 'address' => 'Tehran', 'postal_code' => '123', 'currency' => 'IRR', 'subtotal_toman' => 1000, 'status' => Order::PENDING_PAYMENT]);
        $order->items()->create(['product_variant_id' => $excluded->id, 'sku' => $excluded->sku, 'product_name' => $product->name, 'size' => $excluded->size, 'color_name' => $excluded->color_name, 'unit_price_toman' => 1000, 'quantity' => 1, 'line_total_toman' => 1000]);
        $excluded->reservations()->create(['order_id' => $order->id, 'quantity' => 1, 'status' => 'active', 'expires_at' => now()->addHour(), 'idempotency_key' => 'reserved-matrix']);

        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => $product->name, 'slug' => $product->slug, 'variants_complete' => 1, 'variants' => [$keep->id => ['sku' => $keep->sku, 'size' => $keep->size, 'color_name' => $keep->color_name, 'price_toman' => 1000, 'is_active' => 1]]])->assertRedirect();

        $this->assertDatabaseHas('product_variants', ['id' => $excluded->id, 'is_active' => false, 'stock_quantity' => 3, 'reserved_quantity' => 1]);
        $this->assertDatabaseHas('stock_reservations', ['product_variant_id' => $excluded->id, 'status' => 'active']);
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $excluded->id]);
    }

    public function test_existing_image_can_be_assigned_to_a_variant_or_cleared(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Image set', 'slug' => 'image-set']);
        $variant = $product->variants()->create(['sku' => 'IMAGE-PINK-45', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000]);
        $image = $product->images()->create(['path' => 'products/1/pink.jpg', 'alt_text' => 'Pink', 'sort_order' => 0]);
        Storage::disk('public')->put($image->path, 'image');

        $payload = ['name' => 'Image set', 'slug' => 'image-set', 'image_color' => [$image->id => 'Pink'], 'image_variant' => [$image->id => $variant->id]];
        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)->assertRedirect();
        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'color_name' => 'Pink', 'product_variant_id' => $variant->id]);
        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Image set', 'slug' => 'image-set', 'image_color' => [$image->id => ''], 'image_variant' => [$image->id => '']])->assertRedirect();
        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'color_name' => null, 'product_variant_id' => null]);
    }

    public function test_new_upload_can_be_assigned_to_a_color_and_variant(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'New image item', 'slug' => 'new-image-item']);
        $variant = $product->variants()->create(['sku' => 'NEW-IMAGE-PINK', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'slug' => $product->slug,
            'images' => [UploadedFile::fake()->image('pink.jpg')],
            'image_color' => [0 => 'Pink'], 'image_variant' => [0 => $variant->id],
        ])->assertRedirect();

        $image = $product->fresh()->images()->firstOrFail();
        $this->assertSame('Pink', $image->color_name);
        $this->assertSame($variant->id, $image->product_variant_id);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_image_assignment_rejects_another_products_variant_without_saving_product_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Owned image', 'slug' => 'owned-image']);
        $image = $product->images()->create(['path' => 'products/1/image.jpg', 'alt_text' => 'Image', 'sort_order' => 0]);
        $otherProduct = Product::create(['name' => 'Other product', 'slug' => 'other-product']);
        $otherVariant = $otherProduct->variants()->create(['sku' => 'OTHER-VARIANT', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), ['name' => 'Should roll back', 'slug' => $product->slug, 'image_variant' => [$image->id => $otherVariant->id]])->assertSessionHasErrors('image_variant.'.$image->id);
        $this->assertSame('Owned image', $product->fresh()->name);
        $this->assertNull($image->fresh()->product_variant_id);
    }
}
