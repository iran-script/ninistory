<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase58LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_merchant_verification_is_audited_and_reversible(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Real item', 'slug' => 'real-item', 'is_active' => false]);

        $this->actingAs($admin)->post(route('admin.products.merchant-verification', $product), ['decision' => 'approved'])->assertRedirect();
        $product->refresh();
        $this->assertSame('approved', $product->merchant_verification_status);
        $this->assertSame($admin->id, $product->merchant_verified_by);
        $this->assertNotNull($product->merchant_verified_at);

        $this->actingAs($admin)->post(route('admin.products.merchant-verification', $product), ['decision' => 'rejected', 'reason' => 'عکس نیازمند بررسی است'])->assertRedirect();
        $this->assertSame('rejected', $product->fresh()->merchant_verification_status);
        $this->assertSame('عکس نیازمند بررسی است', $product->fresh()->merchant_review_reason);
    }

    public function test_demo_products_are_excluded_from_product_sitemap(): void
    {
        $category = Category::create(['name' => 'Demo', 'slug' => 'demo-category', 'is_active' => true]);
        $demo = Product::create(['category_id' => $category->id, 'name' => 'Demo item', 'slug' => 'demo-item', 'is_active' => true]);
        $demo->variants()->create(['sku' => 'DEMO-ONE', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 1, 'is_active' => true]);

        $this->get(route('seo.sitemap.products'))->assertOk()->assertDontSee('demo-item');
    }
}
