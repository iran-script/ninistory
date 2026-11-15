<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCsvImportService;
use App\Services\ProductReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase56OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_separates_demo_and_real_product_requirements(): void
    {
        $product = Product::create(['name' => 'Demo', 'slug' => 'demo-dress', 'is_active' => false]);
        $result = app(ProductReadinessService::class)->inspect($product);

        $this->assertTrue($result['is_demo']);
        $this->assertFalse($result['can_publish']);
        $this->assertTrue($result['required']->contains(fn (string $warning): bool => str_contains($warning, 'دموی')));
    }

    public function test_admin_can_edit_and_only_publish_nonempty_business_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.content-pages.update', 'contact'), ['title' => 'تماس با ما', 'status' => 'published', 'body' => 'اطلاعات تماس تأییدشده'])->assertRedirect();
        $this->get(route('content-pages.show', 'contact'))->assertOk()->assertSee('اطلاعات تماس تأییدشده');
    }

    public function test_product_csv_preview_rejects_duplicate_or_unknown_rows_without_writing(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true]);
        $file = UploadedFile::fake()->createWithContent('products.csv', "product_name,category_slug,product_sku,variant_sku,color_name,size,price_toman,stock_quantity\nDress,girls,D-1,V-1,Pink,4,1000,2\nDress,girls,D-1,V-1,Pink,5,1000,2\n");

        $response = $this->actingAs($admin)->post(route('admin.products.import.preview'), ['file' => $file]);

        $response->assertOk()->assertSee('SKU تکراری');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_readiness_dashboard_is_authorized(): void
    {
        $this->get(route('admin.content-readiness'))->assertRedirect(route('admin.login'));
    }
}
