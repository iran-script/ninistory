<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_prices_accept_grouped_persian_digits_and_store_integers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Grouped price product', 'slug' => 'grouped-price-product', 'category_id' => $category->id,
            'variants' => ['new-row' => ['sku' => 'GROUPED-1', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => '۱٬۱۸۰٬۰۰۰', 'compare_at_price_toman' => '١٬٥٠٠٬٠٠٠', 'is_active' => 1]],
        ])->assertRedirect();

        $variant = Product::where('slug', 'grouped-price-product')->firstOrFail()->variants()->firstOrFail();
        $this->assertSame(1180000, $variant->price_toman);
        $this->assertSame(1500000, $variant->compare_at_price_toman);
    }

    public function test_invalid_money_is_rejected_before_persistence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Invalid price product', 'slug' => 'invalid-price-product', 'category_id' => $category->id,
            'variants' => ['new-row' => ['sku' => 'INVALID-1', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => '1.2m', 'is_active' => 1]],
        ])->assertSessionHasErrors('variants.new-row.price_toman');

        $this->assertDatabaseMissing('products', ['slug' => 'invalid-price-product']);
    }
}
