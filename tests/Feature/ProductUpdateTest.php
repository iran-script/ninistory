<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_without_images_can_be_updated_without_uploading_images(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Existing image-less product',
            'slug' => 'existing-image-less-product',
            'is_active' => true,
        ]);
        $firstVariant = $product->variants()->create([
            'sku' => 'IMAGELESS-ONE',
            'size' => '40',
            'color_name' => 'Navy',
            'price_toman' => 1000,
            'is_active' => true,
        ]);
        $secondVariant = $product->variants()->create([
            'sku' => 'IMAGELESS-TWO',
            'size' => '45',
            'color_name' => 'Navy',
            'price_toman' => 1200,
            'is_active' => true,
        ]);
        $editUrl = route('admin.products.edit', $product);

        $response = $this->actingAs($admin)
            ->from($editUrl)
            ->post(route('admin.products.update', $product), [
                '_method' => 'PUT',
                'name' => 'Updated existing image-less product',
                'slug' => $product->slug,
                'is_active' => '1',
                'variants_complete' => '1',
                'variants' => [
                    $firstVariant->id => [
                        'variant_id' => $firstVariant->id,
                        'sku' => $firstVariant->sku,
                        'size' => $firstVariant->size,
                        'color_name' => $firstVariant->color_name,
                        'price_toman' => $firstVariant->price_toman,
                        'is_active' => '1',
                    ],
                    $secondVariant->id => [
                        'variant_id' => $secondVariant->id,
                        'sku' => $secondVariant->sku,
                        'size' => $secondVariant->size,
                        'color_name' => $secondVariant->color_name,
                        'price_toman' => $secondVariant->price_toman,
                        'is_active' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect($editUrl)->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated existing image-less product',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', ['id' => $firstVariant->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $secondVariant->id, 'product_id' => $product->id]);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(0, $product->images()->count());
    }

    public function test_draft_cannot_be_published_without_an_image_and_gets_a_readable_validation_error(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Draft product',
            'slug' => 'draft-product',
            'is_active' => false,
        ]);
        $product->variants()->create([
            'sku' => 'DRAFT-ONE',
            'size' => '40',
            'color_name' => 'Navy',
            'price_toman' => 1000,
            'is_active' => true,
        ]);
        $editUrl = route('admin.products.edit', $product);

        $response = $this->actingAs($admin)
            ->from($editUrl)
            ->post(route('admin.products.update', $product), [
                '_method' => 'PUT',
                'name' => 'Draft product',
                'slug' => $product->slug,
                'is_active' => '1',
                'variants_complete' => '1',
                'variants' => [
                    $product->variants()->first()->id => [
                        'variant_id' => $product->variants()->first()->id,
                        'sku' => 'DRAFT-ONE',
                        'size' => '40',
                        'color_name' => 'Navy',
                        'price_toman' => '1000',
                        'is_active' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect($editUrl)->assertSessionHasErrors('images');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_json_validation_failure_returns_field_errors_instead_of_a_generic_page(): void
    {
        App::setLocale('fa');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Product with invalid input',
            'slug' => 'product-with-invalid-input',
            'is_active' => false,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'VALID-SKU',
            'size' => '40',
            'color_name' => 'Navy',
            'price_toman' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->post(route('admin.products.update', $product), [
                '_method' => 'PUT',
                'name' => '',
                'slug' => $product->slug,
                'is_active' => '0',
                'variants_complete' => '1',
                'variants' => [
                    $variant->id => [
                        'variant_id' => $variant->id,
                        'sku' => $variant->sku,
                        'size' => $variant->size,
                        'color_name' => $variant->color_name,
                        'price_toman' => '1000',
                        'is_active' => '1',
                    ],
                ],
            ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'وارد کردن نام محصول الزامی است.');
    }

    public function test_html_validation_failure_returns_to_the_editor_with_a_persian_error_and_input(): void
    {
        App::setLocale('fa');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Product with invalid input',
            'slug' => 'product-with-invalid-html-input',
            'is_active' => false,
        ]);
        $editUrl = route('admin.products.edit', $product);

        $response = $this->actingAs($admin)
            ->from($editUrl)
            ->put(route('admin.products.update', $product), [
                'name' => '',
                'slug' => $product->slug,
            ]);

        $response->assertRedirect($editUrl)
            ->assertSessionHasErrors(['name' => 'وارد کردن نام محصول الزامی است.'])
            ->assertSessionHasInput('slug', $product->slug);
    }

    public function test_update_accepts_one_then_multiple_images_and_preserves_existing_images_and_variants(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Image editing product',
            'slug' => 'image-editing-product',
            'is_active' => false,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'IMAGE-EDIT-ONE',
            'size' => '40',
            'color_name' => 'Navy',
            'price_toman' => 1000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('products/'.$product->id.'/existing.jpg', 'existing-image');
        $existingImage = $product->images()->create([
            'path' => 'products/'.$product->id.'/existing.jpg',
            'alt_text' => $product->name,
            'sort_order' => 0,
            'is_cover' => true,
        ]);
        $payload = [
            'name' => $product->name,
            'slug' => $product->slug,
            'variants_complete' => '1',
            'variants' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'size' => $variant->size,
                    'color_name' => $variant->color_name,
                    'price_toman' => (string) $variant->price_toman,
                    'is_active' => '1',
                ],
            ],
        ];

        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload + [
            'images' => [UploadedFile::fake()->image('single.jpg')],
        ])->assertRedirect();
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseHas('product_images', ['id' => $existingImage->id, 'path' => $existingImage->path]);

        $imagesBeforeSecondUpdate = $product->fresh()->images->keyBy('id');
        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload + [
            'images' => [UploadedFile::fake()->image('second-a.jpg'), UploadedFile::fake()->image('second-b.jpg')],
            'image_order' => [$existingImage->id => 2],
        ])->assertRedirect();

        $this->assertDatabaseCount('product_images', 4);
        $this->assertDatabaseHas('product_images', ['id' => $existingImage->id, 'path' => $existingImage->path, 'sort_order' => 2]);
        foreach ($imagesBeforeSecondUpdate as $image) {
            $this->assertDatabaseHas('product_images', ['id' => $image->id, 'path' => $image->path]);
        }
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        $newlyUploadedImage = $product->fresh()->images()->whereKeyNot($existingImage->id)->firstOrFail();
        $this->delete(route('admin.products.images.destroy', [$product, $newlyUploadedImage]))->assertRedirect();
        $this->assertDatabaseMissing('product_images', ['id' => $newlyUploadedImage->id]);
        $this->assertDatabaseHas('product_images', ['id' => $existingImage->id, 'path' => $existingImage->path]);
    }
}
